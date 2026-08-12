<?php

namespace App\Jobs;

use App\Models\Export;
use App\services\ArcgisService;
use App\Support\Exports\ExportDataColumns;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExportAttachmentsJob implements ShouldQueue
{
    use Queueable;

    private const SOURCE_BUILDING_ARCGIS = 'building_arcgis';

    private const SOURCE_HOUSING_UNIT_ARCGIS = 'housing_unit_arcgis';

    private const DEFAULT_SOURCES = [
        self::SOURCE_BUILDING_ARCGIS,
        self::SOURCE_HOUSING_UNIT_ARCGIS,
    ];

    public int $tries = 2;

    public int $timeout = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $exportId) {}

    /**
     * Execute the job.
     */
    public function handle(ArcgisService $arcgis): void
    {
        $claimed = Export::query()
            ->whereKey($this->exportId)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'progress' => 0,
                'processed' => 0,
                'file_name' => null,
            ]);

        if ($claimed === 0) {
            return;
        }

        $export = Export::query()->find($this->exportId);

        if (! $export) {
            return;
        }

        try {
            $params = json_decode((string) $export->filters, true) ?: [];
            $sources = $this->selectedSources($params);
            $rows = $this->attachmentRows($params, $sources);

            $fileName = 'exports/attachments_'.now()->timestamp.'.zip';
            $fullPath = storage_path('app/public/'.$fileName);

            if (! is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0777, true);
            }

            $zip = new \ZipArchive;

            if ($zip->open($fullPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create attachments zip file.');
            }

            $token = $arcgis->getToken();
            $indexRows = [];
            $processed = 0;
            $processedArcgisRecords = [];
            $seenPaths = [];
            $totalRows = max(1, $rows->count());

            foreach ($rows as $position => $row) {
                $export->refresh();

                if ($export->status === 'cancelled') {
                    $zip->close();

                    return;
                }

                $buildingRecordKey = self::SOURCE_BUILDING_ARCGIS.':'.$row->building_objectid;

                if (
                    in_array(self::SOURCE_BUILDING_ARCGIS, $sources, true)
                    && filled($row->building_objectid)
                    && ! isset($processedArcgisRecords[$buildingRecordKey])
                ) {
                    $processedArcgisRecords[$buildingRecordKey] = true;
                    $processed += $this->addArcgisAttachments(
                        $zip,
                        $arcgis,
                        $token,
                        0,
                        (string) $row->building_objectid,
                        'building',
                        $row,
                        $params,
                        $indexRows,
                        $seenPaths,
                    );
                }

                $housingRecordKey = self::SOURCE_HOUSING_UNIT_ARCGIS.':'.$row->housing_objectid;

                if (
                    in_array(self::SOURCE_HOUSING_UNIT_ARCGIS, $sources, true)
                    && filled($row->housing_objectid)
                    && ! isset($processedArcgisRecords[$housingRecordKey])
                ) {
                    $processedArcgisRecords[$housingRecordKey] = true;
                    $processed += $this->addArcgisAttachments(
                        $zip,
                        $arcgis,
                        $token,
                        1,
                        (string) $row->housing_objectid,
                        'housing_unit',
                        $row,
                        $params,
                        $indexRows,
                        $seenPaths,
                    );
                }

                if (($position + 1) % 10 === 0) {
                    $export->update([
                        'progress' => min(95, (int) floor((($position + 1) / $totalRows) * 95)),
                        'processed' => $processed,
                    ]);
                }
            }

            if (($params['include_attachment_index'] ?? '1') !== '0') {
                $zip->addFromString('attachments-index.csv', $this->indexCsv($indexRows));
            }

            $zip->close();

            $export->update([
                'status' => 'done',
                'progress' => 100,
                'processed' => $processed,
                'file_name' => $fileName,
            ]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
            ]);

            Log::error('Attachment export failed', [
                'export_id' => $this->exportId,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, string>
     */
    private function selectedSources(array $params): array
    {
        $sources = collect($params['attachment_sources'] ?? self::DEFAULT_SOURCES)
            ->map(fn ($source): string => trim((string) $source))
            ->filter(fn (string $source): bool => in_array($source, self::DEFAULT_SOURCES, true))
            ->unique()
            ->values()
            ->all();

        return $sources === [] ? self::DEFAULT_SOURCES : $sources;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $sources
     */
    private function attachmentRows(array $params, array $sources): \Illuminate\Support\Collection
    {
        $buildingsSource = ExportDataColumns::BUILDINGS_TABLE;
        $housingUnitsSource = ExportDataColumns::HOUSING_UNITS_TABLE;
        $filters = $params['filters'] ?? [];
        $needsHousingJoin = in_array(self::SOURCE_HOUSING_UNIT_ARCGIS, $sources, true)
            || (($params['imported_object_id_target'] ?? 'building') === 'housing_unit')
            || collect(array_keys((array) $filters))
                ->contains(fn (string $field): bool => ExportDataColumns::hasColumn($housingUnitsSource, $field));

        $query = $needsHousingJoin
            ? DB::table("{$housingUnitsSource} as h")->join("{$buildingsSource} as b", 'b.globalid', '=', 'h.parentglobalid')
            : DB::table("{$buildingsSource} as b");

        $query->select([
            'b.objectid as building_objectid',
            'b.globalid as building_globalid',
            'b.owner_name',
        ]);

        if ($needsHousingJoin) {
            $query->addSelect([
                'h.objectid as housing_objectid',
                'h.globalid as housing_globalid',
                'h.housing_unit_number',
            ]);
        } else {
            $query->selectRaw('NULL as housing_objectid, NULL as housing_globalid, NULL as housing_unit_number');
        }

        $this->applyFilters($query, $params);

        return $query
            ->orderBy('b.objectid')
            ->when($needsHousingJoin, fn ($query) => $query->orderBy('h.objectid'))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function applyFilters($query, array $params): void
    {
        $filters = $params['filters'] ?? [];
        $buildingsSource = ExportDataColumns::BUILDINGS_TABLE;
        $housingUnitsSource = ExportDataColumns::HOUSING_UNITS_TABLE;

        foreach ($filters as $field => $values) {
            $values = array_filter((array) $values, fn ($value): bool => $value !== null && $value !== '');

            if ($values === []) {
                continue;
            }

            if ($field === 'building_states_auditig') {
                $query->whereExists(function ($sub) use ($values): void {
                    $sub->select(DB::raw(1))
                        ->from('building_statuses as bs')
                        ->whereColumn('bs.building_id', 'b.objectid')
                        ->whereIn('bs.status_id', $values);
                });

                continue;
            }

            if (ExportDataColumns::hasColumn($buildingsSource, (string) $field)) {
                $query->whereIn("b.{$field}", $values);
            } elseif (ExportDataColumns::hasColumn($housingUnitsSource, (string) $field)) {
                $query->whereIn("h.{$field}", $values);
            }
        }

        $importedObjectIds = collect($params['imported_object_ids'] ?? [])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($importedObjectIds === []) {
            return;
        }

        $target = ($params['imported_object_id_target'] ?? 'building') === 'housing_unit'
            ? 'housing_unit'
            : 'building';

        $query->whereIn($target === 'housing_unit' ? 'h.objectid' : 'b.objectid', $importedObjectIds);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<int, array<string, mixed>>  $indexRows
     * @param  array<string, int>  $seenPaths
     */
    private function addArcgisAttachments(
        \ZipArchive $zip,
        ArcgisService $arcgis,
        string $token,
        int $layerId,
        string $objectId,
        string $recordType,
        object $row,
        array $params,
        array &$indexRows,
        array &$seenPaths,
    ): int {
        $attachments = $arcgis->getAttachments($objectId, $layerId, $token);
        $processed = 0;

        foreach ($attachments as $attachment) {
            $attachmentId = $attachment['id'] ?? null;

            if (! filled($attachmentId)) {
                continue;
            }

            $download = $arcgis->downloadAttachment($objectId, $layerId, $attachmentId, $token);

            if (! ($download['success'] ?? false) || ! isset($download['body'])) {
                continue;
            }

            $zipPath = $this->zipPath($row, $recordType, $attachment, $params, $seenPaths);
            $zip->addFromString($zipPath, (string) $download['body']);

            $indexRows[] = [
                'record_type' => $recordType,
                'building_objectid' => $row->building_objectid,
                'building_globalid' => $row->building_globalid,
                'housing_objectid' => $row->housing_objectid,
                'housing_globalid' => $row->housing_globalid,
                'attachment_id' => $attachmentId,
                'attachment_name' => $attachment['name'] ?? '',
                'content_type' => $attachment['contentType'] ?? '',
                'zip_path' => $zipPath,
            ];

            $processed++;
        }

        return $processed;
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @param  array<string, mixed>  $params
     * @param  array<string, int>  $seenPaths
     */
    private function zipPath(object $row, string $recordType, array $attachment, array $params, array &$seenPaths): string
    {
        $strategy = (string) ($params['attachment_filename_strategy'] ?? 'objectid_type');
        $grouping = (string) ($params['attachment_grouping'] ?? 'by_building');
        $attachmentId = (string) ($attachment['id'] ?? 'attachment');
        $originalName = $this->safeFilename((string) ($attachment['name'] ?? "attachment-{$attachmentId}"));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $nameWithoutExtension = pathinfo($originalName, PATHINFO_FILENAME);

        $recordId = $recordType === 'housing_unit'
            ? (string) ($row->housing_objectid ?? $row->housing_globalid ?? 'housing-unit')
            : (string) ($row->building_objectid ?? $row->building_globalid ?? 'building');

        $baseName = match ($strategy) {
            'globalid' => (string) ($recordType === 'housing_unit' ? $row->housing_globalid : $row->building_globalid),
            'owner_name' => (string) ($row->owner_name ?? $recordId),
            default => $recordId.'_'.$recordType.'_'.$attachmentId,
        };

        $fileName = $this->safeFilename($baseName.'_'.$nameWithoutExtension);
        $fileName = $extension !== '' ? "{$fileName}.{$extension}" : $fileName;

        $folder = match ($grouping) {
            'flat' => '',
            'by_housing_unit' => $recordType === 'housing_unit'
                ? 'housing_units/'.$this->safeFilename((string) ($row->housing_objectid ?? $row->housing_globalid ?? 'unknown')).'/'
                : 'buildings/'.$this->safeFilename((string) ($row->building_objectid ?? $row->building_globalid ?? 'unknown')).'/',
            default => 'buildings/'.$this->safeFilename((string) ($row->building_objectid ?? $row->building_globalid ?? 'unknown')).'/',
        };

        $path = $folder.$fileName;
        $seenPaths[$path] = ($seenPaths[$path] ?? 0) + 1;

        if ($seenPaths[$path] === 1) {
            return $path;
        }

        $suffix = $seenPaths[$path];
        $pathInfo = pathinfo($path);
        $duplicateName = ($pathInfo['filename'] ?? 'attachment')."_{$suffix}";

        if (($pathInfo['extension'] ?? '') !== '') {
            $duplicateName .= '.'.$pathInfo['extension'];
        }

        return trim(($pathInfo['dirname'] ?? '') === '.' ? '' : ($pathInfo['dirname'] ?? '').'/').$duplicateName;
    }

    private function safeFilename(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\\\\\/:*?"<>|\r\n\t]+/u', '-', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = trim($value, ' .-_');

        return $value !== '' ? $value : 'attachment';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function indexCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        $headers = [
            'record_type',
            'building_objectid',
            'building_globalid',
            'housing_objectid',
            'housing_globalid',
            'attachment_id',
            'attachment_name',
            'content_type',
            'zip_path',
        ];

        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, collect($headers)->map(fn (string $header) => $row[$header] ?? '')->all());
        }

        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
