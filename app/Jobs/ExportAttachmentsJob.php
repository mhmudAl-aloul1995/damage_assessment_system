<?php

namespace App\Jobs;

use App\Models\Export;
use App\services\ArcgisService;
use App\Support\Exports\ExportDataColumns;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as PhpSpreadsheetWriter;

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
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $attachmentInfoCache = [];

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

            if (($params['export_mode'] ?? 'data') === 'data' && $this->shouldIncludeAttachmentExcelColumns($params)) {
                $fileName = 'exports/export_'.now()->timestamp.'.xlsx';
                $fullPath = storage_path('app/public/'.$fileName);

                $token = $arcgis->getToken();
                $this->writeDataWorkbook($fullPath, $params, $sources, $arcgis, $token);

                $export->update([
                    'status' => 'done',
                    'progress' => 100,
                    'processed' => $this->dataRows($params, $sources, $this->dataColumns($params))->count(),
                    'file_name' => $fileName,
                ]);

                return;
            }

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

            if (($params['export_mode'] ?? null) === 'data_with_attachments') {
                $this->addDataWorkbookToZip($zip, $params, $sources, $arcgis, $token);
            }

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
            ? DB::table("{$buildingsSource} as b")->leftJoin("{$housingUnitsSource} as h", 'b.globalid', '=', 'h.parentglobalid')
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
        $typeFilters = $this->selectedAttachmentTypeFilters($params);

        foreach ($attachments as $attachment) {
            $attachmentId = $attachment['id'] ?? null;

            if (! filled($attachmentId)) {
                continue;
            }

            if (! $this->matchesAttachmentTypeFilters($attachment, $typeFilters)) {
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
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $sources
     */
    private function addDataWorkbookToZip(\ZipArchive $zip, array $params, array $sources, ArcgisService $arcgis, string $token): void
    {
        $dataPath = storage_path('app/public/exports/data_'.$this->exportId.'_'.now()->timestamp.'.xlsx');

        $this->writeDataWorkbook($dataPath, $params, $sources, $arcgis, $token);
        $zip->addFile($dataPath, 'data.xlsx');
        register_shutdown_function(static function () use ($dataPath): void {
            if (is_file($dataPath)) {
                unlink($dataPath);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $sources
     */
    private function writeDataWorkbook(string $dataPath, array $params, array $sources, ArcgisService $arcgis, string $token): void
    {
        if (
            ($params['export_mode'] ?? null) === 'data_with_attachments'
            && ($params['attachment_excel_display'] ?? 'links') === 'links'
            && $this->attachmentExcelColumns($params) !== []
        ) {
            $this->writeDataWorkbookWithZipLinks($dataPath, $params, $sources, $arcgis, $token);

            return;
        }

        if (
            $this->shouldIncludeAttachmentExcelColumns($params)
            && ($params['attachment_excel_display'] ?? 'links') === 'images'
            && $this->attachmentExcelColumns($params) !== []
        ) {
            $this->writeDataWorkbookWithImages($dataPath, $params, $sources, $arcgis, $token);

            return;
        }

        if (! is_dir(dirname($dataPath))) {
            mkdir(dirname($dataPath), 0777, true);
        }

        $writer = new Writer;
        $writer->openToFile($dataPath);

        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontSize(12)
            ->setFontColor('FFFFFF')
            ->setBackgroundColor('1F4E78')
            ->setCellAlignment(CellAlignment::CENTER);

        $columns = $this->dataColumns($params);
        $attachmentColumns = $this->attachmentExcelColumns($params);
        $labels = $this->assessmentLabels();
        $writer->addRow(Row::fromValues(
            collect($columns)
                ->map(fn (array $column): string => $this->columnLabel($column['field'], $labels))
                ->merge(collect($attachmentColumns)->pluck('label'))
                ->all(),
            $headerStyle,
        ));

        $this->dataRows($params, $sources, $columns)
            ->each(function (object $row) use ($writer, $columns, $attachmentColumns, $params, $sources, $arcgis, $token): void {
                $writer->addRow(Row::fromValues(
                    collect($columns)
                        ->map(fn (array $column): mixed => $row->{$column['alias']} ?? null)
                        ->merge($this->attachmentExcelValues($row, $attachmentColumns, $params, $sources, $arcgis, $token))
                        ->all(),
                ));
            });

        $writer->close();
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $sources
     */
    private function writeDataWorkbookWithZipLinks(string $dataPath, array $params, array $sources, ArcgisService $arcgis, string $token): void
    {
        if (! is_dir(dirname($dataPath))) {
            mkdir(dirname($dataPath), 0777, true);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $columns = $this->dataColumns($params);
        $attachmentColumns = $this->attachmentExcelColumns($params);
        $labels = $this->assessmentLabels();
        $seenPaths = [];

        $headers = collect($columns)
            ->map(fn (array $column): string => $this->columnLabel($column['field'], $labels))
            ->merge(collect($attachmentColumns)->pluck('label'))
            ->values()
            ->all();

        foreach ($headers as $index => $header) {
            $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($columnLetter.'1', $header);
            $sheet->getColumnDimension($columnLetter)->setWidth($index < count($columns) ? 16 : 24);
        }

        $sheet->getStyle('1:1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('1:1')->getFill()->setFillType('solid')->getStartColor()->setRGB('1F4E78');
        $sheet->getStyle('1:1')->getAlignment()->setHorizontal('center');

        $rowIndex = 2;

        foreach ($this->dataRows($params, $sources, $columns) as $row) {
            foreach ($columns as $index => $column) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$rowIndex, $row->{$column['alias']} ?? null);
            }

            foreach ($attachmentColumns as $attachmentIndex => $attachmentColumn) {
                $columnIndex = count($columns) + $attachmentIndex + 1;
                $cellCoordinate = Coordinate::stringFromColumnIndex($columnIndex).$rowIndex;
                $links = $this->attachmentZipLinks($row, $attachmentColumn, $params, $sources, $arcgis, $token, $seenPaths);

                if ($links === []) {
                    $sheet->setCellValue($cellCoordinate, 'لا توجد مرفقات مطابقة');
                    $sheet->getStyle($cellCoordinate)->getAlignment()->setWrapText(true);

                    continue;
                }

                $linkText = 'فتح '.$attachmentColumn['label'].(count($links) > 1 ? ' ('.count($links).')' : '');
                $sheet->setCellValue($cellCoordinate, $linkText);
                $sheet->getCell($cellCoordinate)->getHyperlink()->setUrl($links[0]['path']);
                $sheet->getCell($cellCoordinate)->getHyperlink()->setTooltip($links[0]['name']);
                $sheet->getStyle($cellCoordinate)->getFont()->getColor()->setRGB('0563C1');
                $sheet->getStyle($cellCoordinate)->getFont()->setUnderline(true);
            }

            $rowIndex++;
        }

        (new PhpSpreadsheetWriter($spreadsheet))->save($dataPath);
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $sources
     */
    private function writeDataWorkbookWithImages(string $dataPath, array $params, array $sources, ArcgisService $arcgis, string $token): void
    {
        if (! is_dir(dirname($dataPath))) {
            mkdir(dirname($dataPath), 0777, true);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $columns = $this->dataColumns($params);
        $attachmentColumns = $this->attachmentExcelColumns($params);
        $labels = $this->assessmentLabels();
        $temporaryImages = [];

        $headers = collect($columns)
            ->map(fn (array $column): string => $this->columnLabel($column['field'], $labels))
            ->merge(collect($attachmentColumns)->pluck('label'))
            ->values()
            ->all();

        if ($headers === []) {
            $headers = ['ObjectID'];
            $columns = [[
                'table' => 'building',
                'field' => 'objectid',
                'alias' => 'building_objectid',
            ]];
        }

        foreach ($headers as $index => $header) {
            $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($columnLetter.'1', $header);
            $sheet->getColumnDimension($columnLetter)->setWidth($index < count($columns) ? 16 : 24);
        }

        $sheet->getStyle('1:1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('1:1')->getFill()->setFillType('solid')->getStartColor()->setRGB('1F4E78');
        $sheet->getStyle('1:1')->getAlignment()->setHorizontal('center');

        $rowIndex = 2;

        try {
            foreach ($this->dataRows($params, $sources, $columns) as $row) {
                foreach ($columns as $index => $column) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$rowIndex, $row->{$column['alias']} ?? null);
                }

                foreach ($attachmentColumns as $attachmentIndex => $attachmentColumn) {
                    $columnIndex = count($columns) + $attachmentIndex + 1;
                    $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
                    $cellCoordinate = $columnLetter.$rowIndex;
                    $entries = $this->attachmentExcelEntries($row, $attachmentColumn, $sources, $arcgis, $token);
                    $images = collect($entries)->filter(fn (array $entry): bool => $this->isSupportedExcelImage($entry['attachment']))->values();
                    $nonImageNames = collect($entries)
                        ->reject(fn (array $entry): bool => $this->isSupportedExcelImage($entry['attachment']))
                        ->map(fn (array $entry): string => (string) ($entry['attachment']['name'] ?? $entry['attachment']['id'] ?? 'attachment'))
                        ->values();

                    if ($entries === []) {
                        $sheet->setCellValue($cellCoordinate, 'لا توجد مرفقات مطابقة');
                        $sheet->getStyle($cellCoordinate)->getAlignment()->setWrapText(true);
                    } elseif ($images->isEmpty() && $nonImageNames->isEmpty()) {
                        $sheet->setCellValue($cellCoordinate, 'لا توجد صور قابلة للعرض');
                        $sheet->getStyle($cellCoordinate)->getAlignment()->setWrapText(true);
                    } elseif ($nonImageNames->isNotEmpty()) {
                        $sheet->setCellValue($cellCoordinate, $nonImageNames->implode("\n"));
                        $sheet->getStyle($cellCoordinate)->getAlignment()->setWrapText(true);
                    }

                    if ($images->isEmpty()) {
                        continue;
                    }

                    $imagesPerLine = 3;
                    $thumbHeight = 76;
                    $horizontalStep = 108;
                    $verticalStep = 86;
                    $lines = (int) ceil($images->count() / $imagesPerLine);
                    $sheet->getRowDimension($rowIndex)->setRowHeight(max($sheet->getRowDimension($rowIndex)->getRowHeight(), $lines * 68));
                    $sheet->getColumnDimension($columnLetter)->setWidth(max(
                        $sheet->getColumnDimension($columnLetter)->getWidth(),
                        min(52, $images->count() * 16)
                    ));

                    foreach ($images as $imageIndex => $entry) {
                        $attachment = $entry['attachment'];
                        $attachmentId = $attachment['id'] ?? null;

                        if (! filled($attachmentId)) {
                            continue;
                        }

                        $download = $arcgis->downloadAttachment($entry['object_id'], $entry['layer_id'], $attachmentId, $token);

                        if (! ($download['success'] ?? false) || ! isset($download['body'])) {
                            continue;
                        }

                        $extension = $this->excelImageExtension($attachment);
                        $temporaryImage = tempnam(sys_get_temp_dir(), 'export-attachment-');

                        if ($temporaryImage === false) {
                            continue;
                        }

                        $temporaryImageWithExtension = $temporaryImage.'.'.$extension;
                        rename($temporaryImage, $temporaryImageWithExtension);
                        file_put_contents($temporaryImageWithExtension, (string) $download['body']);
                        $temporaryImages[] = $temporaryImageWithExtension;

                        $drawing = new Drawing;
                        $drawing->setName((string) ($attachment['name'] ?? 'attachment'));
                        $drawing->setPath($temporaryImageWithExtension);
                        $drawing->setCoordinates($cellCoordinate);
                        $drawing->setHeight($thumbHeight);
                        $drawing->setOffsetX(6 + (($imageIndex % $imagesPerLine) * $horizontalStep));
                        $drawing->setOffsetY(6 + ((int) floor($imageIndex / $imagesPerLine) * $verticalStep));
                        $drawing->setWorksheet($sheet);
                    }
                }

                $rowIndex++;
            }

            (new PhpSpreadsheetWriter($spreadsheet))->save($dataPath);
        } finally {
            foreach ($temporaryImages as $temporaryImage) {
                if (is_file($temporaryImage)) {
                    unlink($temporaryImage);
                }
            }

            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array{table: string, field: string, alias: string}>
     */
    private function dataColumns(array $params): array
    {
        $buildingColumns = ExportDataColumns::sanitizeRequestedColumns(
            ExportDataColumns::BUILDINGS_TABLE,
            array_values($params['building_columns'] ?? []),
            [ExportDataColumns::BUILDING_UNITS_COUNT_COLUMN],
        );

        $housingColumns = ExportDataColumns::sanitizeRequestedColumns(
            ExportDataColumns::HOUSING_UNITS_TABLE,
            array_values($params['housing_columns'] ?? []),
        );

        if ($buildingColumns === [] && $housingColumns === []) {
            $buildingColumns = ['objectid', 'globalid', 'owner_name'];
        }

        return collect($buildingColumns)
            ->map(fn (string $field): array => [
                'table' => 'building',
                'field' => $field,
                'alias' => 'building_'.$field,
            ])
            ->merge(collect($housingColumns)->map(fn (string $field): array => [
                'table' => 'housing',
                'field' => $field,
                'alias' => 'housing_'.$field,
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array{type: string, label: string}>
     */
    private function attachmentExcelColumns(array $params): array
    {
        if (! $this->shouldIncludeAttachmentExcelColumns($params)) {
            return [];
        }

        $labels = [
            'all' => 'كل المرفقات',
            'images' => 'صور فقط',
            'pdf' => 'PDF فقط',
            'damage_photos' => 'صور الضرر',
            'identity' => 'مرفقات الهوية',
            'ownership' => 'وثائق الملكية',
            'permit' => 'رخصة البلدية',
            'other_documents' => 'مستندات أخرى',
        ];

        return collect($this->selectedAttachmentTypeFilters($params))
            ->map(fn (string $type): array => [
                'type' => $type,
                'label' => $labels[$type] ?? ucwords(str_replace('_', ' ', $type)),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{type: string, label: string}>  $attachmentColumns
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $sources
     * @return array<int, string>
     */
    private function attachmentExcelValues(
        object $row,
        array $attachmentColumns,
        array $params,
        array $sources,
        ArcgisService $arcgis,
        string $token,
    ): array {
        if ($attachmentColumns === []) {
            return [];
        }

        $records = [];

        if (in_array(self::SOURCE_BUILDING_ARCGIS, $sources, true) && filled($row->__building_objectid ?? null)) {
            $records[] = [
                'layer_id' => 0,
                'object_id' => (string) $row->__building_objectid,
            ];
        }

        if (in_array(self::SOURCE_HOUSING_UNIT_ARCGIS, $sources, true) && filled($row->__housing_objectid ?? null)) {
            $records[] = [
                'layer_id' => 1,
                'object_id' => (string) $row->__housing_objectid,
            ];
        }

        return collect($attachmentColumns)
            ->map(function (array $column) use ($records, $arcgis, $token): string {
                $values = collect($records)
                    ->flatMap(function (array $record) use ($column, $arcgis, $token): array {
                        return collect($this->cachedArcgisAttachments($arcgis, $record['object_id'], $record['layer_id'], $token))
                            ->filter(fn (array $attachment): bool => $this->matchesAttachmentTypeFilters($attachment, [$column['type']]))
                            ->map(function (array $attachment) use ($record, $arcgis, $token): string {
                                $attachmentId = $attachment['id'] ?? null;
                                $name = (string) ($attachment['name'] ?? $attachmentId ?? 'attachment');

                                if (! filled($attachmentId)) {
                                    return $name;
                                }

                                return $arcgis->buildUrl($record['object_id'], $attachmentId, $record['layer_id'], $token);
                            })
                            ->all();
                    })
                    ->filter()
                    ->unique()
                    ->values();

                return $values->implode("\n");
            })
            ->all();
    }

    /**
     * @param  array{type: string, label: string}  $attachmentColumn
     * @param  array<int, string>  $sources
     * @return array<int, array{layer_id: int, object_id: string, attachment: array<string, mixed>}>
     */
    private function attachmentExcelEntries(
        object $row,
        array $attachmentColumn,
        array $sources,
        ArcgisService $arcgis,
        string $token,
    ): array {
        $records = [];

        if (in_array(self::SOURCE_BUILDING_ARCGIS, $sources, true) && filled($row->__building_objectid ?? null)) {
            $records[] = [
                'layer_id' => 0,
                'object_id' => (string) $row->__building_objectid,
            ];
        }

        if (in_array(self::SOURCE_HOUSING_UNIT_ARCGIS, $sources, true) && filled($row->__housing_objectid ?? null)) {
            $records[] = [
                'layer_id' => 1,
                'object_id' => (string) $row->__housing_objectid,
            ];
        }

        return collect($records)
            ->flatMap(function (array $record) use ($attachmentColumn, $arcgis, $token): array {
                return collect($this->cachedArcgisAttachments($arcgis, $record['object_id'], $record['layer_id'], $token))
                    ->filter(fn (array $attachment): bool => $this->matchesAttachmentTypeFilters($attachment, [$attachmentColumn['type']]))
                    ->map(fn (array $attachment): array => [
                        'layer_id' => $record['layer_id'],
                        'object_id' => $record['object_id'],
                        'attachment' => $attachment,
                    ])
                    ->all();
            })
            ->unique(fn (array $entry): string => $entry['layer_id'].':'.$entry['object_id'].':'.($entry['attachment']['id'] ?? $entry['attachment']['name'] ?? 'attachment'))
            ->values()
            ->all();
    }

    /**
     * @param  array{type: string, label: string}  $attachmentColumn
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $sources
     * @param  array<string, int>  $seenPaths
     * @return array<int, array{path: string, name: string}>
     */
    private function attachmentZipLinks(
        object $row,
        array $attachmentColumn,
        array $params,
        array $sources,
        ArcgisService $arcgis,
        string $token,
        array &$seenPaths,
    ): array {
        return collect($this->attachmentExcelEntries($row, $attachmentColumn, $sources, $arcgis, $token))
            ->map(function (array $entry) use ($row, $params, &$seenPaths): array {
                $recordType = (int) $entry['layer_id'] === 1 ? 'housing_unit' : 'building';

                return [
                    'path' => $this->zipPath($this->attachmentZipRow($row), $recordType, $entry['attachment'], $params, $seenPaths),
                    'name' => (string) ($entry['attachment']['name'] ?? $entry['attachment']['id'] ?? 'attachment'),
                ];
            })
            ->values()
            ->all();
    }

    private function attachmentZipRow(object $row): object
    {
        return (object) [
            'building_objectid' => $row->__building_objectid ?? $row->building_objectid ?? null,
            'building_globalid' => $row->__building_globalid ?? $row->building_globalid ?? null,
            'housing_objectid' => $row->__housing_objectid ?? $row->housing_objectid ?? null,
            'housing_globalid' => $row->__housing_globalid ?? $row->housing_globalid ?? null,
            'owner_name' => $row->building_owner_name ?? $row->owner_name ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function isSupportedExcelImage(array $attachment): bool
    {
        $contentType = mb_strtolower((string) ($attachment['contentType'] ?? ''));
        $extension = $this->excelImageExtension($attachment);

        return str_starts_with($contentType, 'image/')
            && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true);
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function excelImageExtension(array $attachment): string
    {
        $extension = mb_strtolower(pathinfo((string) ($attachment['name'] ?? ''), PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true)) {
            return $extension;
        }

        return match (mb_strtolower((string) ($attachment['contentType'] ?? ''))) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp', 'image/x-ms-bmp' => 'bmp',
            default => 'png',
        };
    }

    private function shouldIncludeAttachmentExcelColumns(array $params): bool
    {
        return (string) ($params['include_attachment_excel_columns'] ?? '0') === '1';
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function columnLabel(string $field, array $labels): string
    {
        $label = trim((string) ($labels[$field] ?? ''));

        return $label !== '' ? $label : ucwords(str_replace('_', ' ', $field));
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $sources
     * @param  array<int, array{table: string, field: string, alias: string}>  $columns
     */
    private function dataRows(array $params, array $sources, array $columns): \Illuminate\Support\Collection
    {
        $buildingsSource = ExportDataColumns::BUILDINGS_TABLE;
        $housingUnitsSource = ExportDataColumns::HOUSING_UNITS_TABLE;
        $filters = $params['filters'] ?? [];
        $needsHousingJoin = in_array(self::SOURCE_HOUSING_UNIT_ARCGIS, $sources, true)
            || collect($columns)->contains(fn (array $column): bool => $column['table'] === 'housing')
            || (($params['imported_object_id_target'] ?? 'building') === 'housing_unit')
            || collect(array_keys((array) $filters))
                ->contains(fn (string $field): bool => ExportDataColumns::hasColumn($housingUnitsSource, $field));

        $query = $needsHousingJoin
            ? DB::table("{$buildingsSource} as b")->leftJoin("{$housingUnitsSource} as h", 'b.globalid', '=', 'h.parentglobalid')
            : DB::table("{$buildingsSource} as b");

        $selects = collect($columns)
            ->map(function (array $column): string {
                if ($column['field'] === ExportDataColumns::BUILDING_UNITS_COUNT_COLUMN) {
                    return '(SELECT COUNT(*) FROM '.ExportDataColumns::HOUSING_UNITS_TABLE.' hu_count WHERE hu_count.parentglobalid = b.globalid) as `'.$column['alias'].'`';
                }

                $tableAlias = $column['table'] === 'housing' ? 'h' : 'b';

                return "{$tableAlias}.`{$column['field']}` as `{$column['alias']}`";
            })
            ->all();

        $selects[] = 'b.objectid as `__building_objectid`';
        $selects[] = 'b.globalid as `__building_globalid`';

        if ($needsHousingJoin) {
            $selects[] = 'h.objectid as `__housing_objectid`';
            $selects[] = 'h.globalid as `__housing_globalid`';
        } else {
            $selects[] = 'NULL as `__housing_objectid`';
            $selects[] = 'NULL as `__housing_globalid`';
        }

        $query->selectRaw(implode(', ', $selects));
        $this->applyFilters($query, $params);

        return $query
            ->orderBy('b.objectid')
            ->when($needsHousingJoin, fn ($query) => $query->orderBy('h.objectid'))
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cachedArcgisAttachments(ArcgisService $arcgis, string $objectId, int $layerId, string $token): array
    {
        $key = $layerId.':'.$objectId;

        if (! array_key_exists($key, $this->attachmentInfoCache)) {
            $this->attachmentInfoCache[$key] = $arcgis->getAttachments($objectId, $layerId, $token);
        }

        return $this->attachmentInfoCache[$key];
    }

    /**
     * @return array<string, string>
     */
    private function assessmentLabels(): array
    {
        $labels = DB::table('assessments')
            ->whereNotNull('name')
            ->select('name', 'label')
            ->get()
            ->mapWithKeys(fn (object $item): array => [trim((string) $item->name) => trim((string) ($item->label ?? ''))])
            ->toArray();

        $labels[ExportDataColumns::BUILDING_UNITS_COUNT_COLUMN] = 'عدد الوحدات للمبنى';

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, string>
     */
    private function selectedAttachmentTypeFilters(array $params): array
    {
        $types = collect($params['attachment_type_filters'] ?? ['all'])
            ->map(fn ($type): string => trim((string) $type))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $types === [] ? ['all'] : $types;
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @param  array<int, string>  $typeFilters
     */
    private function matchesAttachmentTypeFilters(array $attachment, array $typeFilters): bool
    {
        if (in_array('all', $typeFilters, true)) {
            return true;
        }

        $searchableText = $this->attachmentSearchableText($attachment);
        $contentType = mb_strtolower((string) ($attachment['contentType'] ?? ''));
        $extension = mb_strtolower(pathinfo((string) ($attachment['name'] ?? ''), PATHINFO_EXTENSION));

        foreach ($typeFilters as $type) {
            if ($type === 'images' && (str_starts_with($contentType, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true))) {
                return true;
            }

            if ($type === 'pdf' && ($contentType === 'application/pdf' || $extension === 'pdf')) {
                return true;
            }

            if ($type === 'damage_photos' && $this->containsAny($searchableText, ['damage', 'damaged', 'damge', 'photo', 'image', 'ضرر', 'اضرار', 'أضرار', 'صورة', 'صور'])) {
                return true;
            }

            if ($type === 'identity' && $this->containsAny($searchableText, ['identity', ' id ', 'id_', '_id', 'id-', '-id', 'passport', 'هوية', 'الهويه', 'الهوية', 'جواز'])) {
                return true;
            }

            if ($type === 'ownership' && $this->containsAny($searchableText, ['ownership', 'owner', 'deed', 'title', 'land', 'ملكية', 'الملكية', 'طابو', 'سند', 'ارض', 'أرض'])) {
                return true;
            }

            if ($type === 'permit' && $this->containsAny($searchableText, ['permit', 'municipal', 'municipality', 'license', 'licence', 'رخصة', 'رخصه', 'بلدية', 'البلدية'])) {
                return true;
            }

            if ($type === 'other_documents' && ! str_starts_with($contentType, 'image/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function attachmentSearchableText(array $attachment): string
    {
        $parts = [
            $attachment['name'] ?? '',
            $attachment['contentType'] ?? '',
            $attachment['keywords'] ?? '',
            $attachment['globalId'] ?? '',
            $attachment['parentGlobalId'] ?? '',
        ];

        return ' '.mb_strtolower(collect($parts)
            ->flatten()
            ->map(fn ($value): string => (string) $value)
            ->implode(' ')).' ';
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
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
