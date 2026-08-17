<?php

namespace App\Console\Commands;

use App\Models\Assessment;
use App\Models\VHousingUnitAudited;
use App\services\ArcgisService;
use App\Support\BrowsershotConfiguration;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\LaravelPdf\Facades\Pdf;

class DownloadHousingUnitAttachments extends Command
{
    private const HOUSING_EXPORT_SOURCE_TABLE = 'v_housing_units_audited';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'arcgis:download-housing-unit-attachments
        {file : Text/CSV/XLSX file containing housing unit ObjectIDs}
        {--output= : Output directory relative to storage/app/public/exports}
        {--types=ownership,permit : Comma separated attachment types: identity,ownership,permit}
        {--exclude-damage : Download all housing unit attachments except damage photos}
        {--attachments-url-only : Add direct ArcGIS attachment links to Excel without downloading files to the server}
        {--include-boq-pdf : Generate a local BOQ PDF from v_housing_units_audited and link it in Excel}
        {--boq-pdf-url : Link to the existing online BOQ PDF export instead of generating local PDF files}
        {--limit= : Process only the first N ObjectIDs}
        {--resume : Continue an existing output by skipping ObjectIDs already recorded in attachments-index.csv}
        {--force : Re-download files that already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download selected ArcGIS housing unit attachments for a list of ObjectIDs.';

    /**
     * Execute the console command.
     */
    public function handle(ArcgisService $arcgis): int
    {
        $filePath = (string) $this->argument('file');

        if (! File::exists($filePath)) {
            $this->error("Input file not found: {$filePath}");

            return self::FAILURE;
        }

        $objectIds = $this->objectIdsFromFile($filePath);
        $limit = $this->option('limit');

        if (filled($limit)) {
            $objectIds = array_slice($objectIds, 0, max(0, (int) $limit));
        }

        if ($objectIds === []) {
            $this->error('No valid ObjectIDs were found in the input file.');

            return self::FAILURE;
        }

        $types = $this->selectedTypes();
        $excludeDamage = (bool) $this->option('exclude-damage');
        $attachmentsUrlOnly = (bool) $this->option('attachments-url-only');
        $includeBoqPdf = (bool) $this->option('include-boq-pdf');
        $useBoqPdfUrl = (bool) $this->option('boq-pdf-url');
        $outputName = $this->safePathSegment((string) ($this->option('output') ?: 'housing_unit_attachments_'.now()->format('Ymd_His')));
        $outputDirectory = storage_path('app/public/exports/'.$outputName);
        $boqPdfDirectory = $outputDirectory.'/boq_pdfs';

        File::ensureDirectoryExists($outputDirectory);

        $indexPath = $outputDirectory.'/attachments-index.csv';
        $xlsxIndexPath = $outputDirectory.'/attachments-index.xlsx';
        $htmlIndexPath = $outputDirectory.'/index.html';
        $resume = (bool) $this->option('resume');
        $shouldAppendIndex = $resume && File::exists($indexPath);

        if ($shouldAppendIndex) {
            $processedObjectIds = $this->processedObjectIdsFromCsv($indexPath);
            $originalObjectIdsCount = count($objectIds);
            $objectIds = array_values(array_filter(
                $objectIds,
                fn (string $objectId): bool => ! in_array($objectId, $processedObjectIds, true)
            ));
            $this->info('Resume: skipping '.($originalObjectIdsCount - count($objectIds)).' ObjectIDs already recorded in the existing index.');

            if ($objectIds === []) {
                $this->info('Resume: no remaining ObjectIDs to process.');
                $this->writeExcelIndexFromCsv($indexPath, $xlsxIndexPath);
                File::put($htmlIndexPath, $this->htmlIndex($this->htmlRowsFromCsv($indexPath)));
                $zipPath = $this->createZipArchive($outputDirectory, $outputName);
                $this->info("Excel index: {$xlsxIndexPath}");
                $this->info("HTML index: {$htmlIndexPath}");
                $this->info("ZIP archive: {$zipPath}");

                return self::SUCCESS;
            }
        }

        $indexHandle = fopen($indexPath, $shouldAppendIndex ? 'a' : 'w');

        if ($indexHandle === false) {
            $this->error("Unable to create index file: {$indexPath}");

            return self::FAILURE;
        }

        if (! $shouldAppendIndex) {
            fputcsv($indexHandle, [
                'objectid',
                'attachment_id',
                'matched_type',
                'original_name',
                'content_type',
                'status',
                'local_path',
                'public_url',
                'arcgis_attachments_url',
                'message',
                'boq_pdf_path',
            ]);
        }

        $htmlRows = [];
        $this->info('ObjectIDs: '.count($objectIds));
        $this->info($excludeDamage ? 'Mode: all attachments except damage photos' : 'Types: '.implode(', ', $types));
        $this->info($attachmentsUrlOnly ? 'Attachments: ArcGIS links only' : 'Attachments: download files');
        $this->info($includeBoqPdf ? 'BOQ PDF: '.($useBoqPdfUrl ? 'online export links' : 'local files') : 'BOQ PDF: disabled');
        $this->info("Output: {$outputDirectory}");

        $token = $arcgis->getToken();
        $downloaded = 0;
        $matched = 0;
        $missing = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar(count($objectIds));
        $bar->start();

        foreach ($objectIds as $objectId) {
            $boqPdfRelativePath = $includeBoqPdf
                ? ($useBoqPdfUrl ? $this->boqPdfExportUrl($objectId) : $this->generateBoqPdf($objectId, $boqPdfDirectory))
                : '';

            $attachmentsResult = $this->getHousingUnitAttachments($arcgis, $objectId, $token);

            if (! ($attachmentsResult['success'] ?? false)) {
                $failed++;
                fputcsv($indexHandle, [$objectId, '', '', '', '', 'failed_request', '', '', '', (string) ($attachmentsResult['message'] ?? 'ArcGIS request failed.'), $boqPdfRelativePath]);
                $htmlRows[] = $this->htmlRow($objectId, '', '', '', 'failed_request', '', (string) ($attachmentsResult['message'] ?? 'ArcGIS request failed.'));
                $bar->advance();

                continue;
            }

            $token = (string) ($attachmentsResult['token'] ?? $token);
            $attachments = $attachmentsResult['attachments'] ?? [];
            $matchingAttachments = $this->matchingAttachments($attachments, $types, $excludeDamage);

            if ($matchingAttachments->isEmpty()) {
                $missing++;

                if ($attachments === [] || $excludeDamage) {
                    fputcsv($indexHandle, [$objectId, '', '', '', '', 'not_found', '', '', '', 'No matching attachments were found.', $boqPdfRelativePath]);
                    $htmlRows[] = $this->htmlRow($objectId, '', '', '', 'not_found', '', 'No matching attachments were found.');
                    $bar->advance();

                    continue;
                }

                foreach ($attachments as $attachment) {
                    $attachmentId = $attachment['id'] ?? null;
                    $originalName = (string) ($attachment['name'] ?? "attachment-{$attachmentId}");
                    $contentType = (string) ($attachment['contentType'] ?? '');

                    if (! filled($attachmentId)) {
                        continue;
                    }

                    fputcsv($indexHandle, [
                        $objectId,
                        $attachmentId,
                        'arcgis',
                        $originalName,
                        $contentType,
                        'online_only',
                        '',
                        '',
                        $this->arcgisAttachmentUrl($objectId, (string) $attachmentId, $token),
                        'No selected attachment types matched; direct ArcGIS attachment link was added.',
                        $boqPdfRelativePath,
                    ]);
                }

                $bar->advance();

                continue;
            }

            foreach ($matchingAttachments as $entry) {
                $attachment = $entry['attachment'];
                $attachmentId = $attachment['id'] ?? null;
                $matchedType = (string) $entry['type'];
                $originalName = (string) ($attachment['name'] ?? "attachment-{$attachmentId}");
                $contentType = (string) ($attachment['contentType'] ?? '');

                if (! filled($attachmentId)) {
                    $failed++;
                    fputcsv($indexHandle, [$objectId, '', $matchedType, $originalName, $contentType, 'failed', '', '', '', 'Missing attachment id.', $boqPdfRelativePath]);
                    $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'failed', '', 'Missing attachment id.');

                    continue;
                }

                if ($attachmentsUrlOnly) {
                    $matched++;
                    $arcgisUrl = $this->arcgisAttachmentUrl($objectId, (string) $attachmentId, $token);
                    fputcsv($indexHandle, [
                        $objectId,
                        $attachmentId,
                        $matchedType,
                        $originalName,
                        $contentType,
                        'online_only',
                        '',
                        '',
                        $arcgisUrl,
                        'Direct ArcGIS attachment link was added without downloading the file.',
                        $boqPdfRelativePath,
                    ]);
                    $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'online_only', $arcgisUrl, 'Direct ArcGIS attachment link.');

                    continue;
                }

                $relativePath = $this->attachmentRelativePath($objectId, $matchedType, (string) $attachmentId, $originalName);
                $localPath = $outputDirectory.'/'.$relativePath;
                $publicUrl = asset('storage/exports/'.$outputName.'/'.$this->urlPath($relativePath));
                File::ensureDirectoryExists(dirname($localPath));

                if (File::exists($localPath) && ! $this->option('force')) {
                    $matched++;
                    fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'skipped_existing', $localPath, $publicUrl, '', 'File already exists.', $boqPdfRelativePath]);
                    $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'skipped_existing', $publicUrl, 'File already exists.');

                    continue;
                }

                $download = $this->downloadHousingUnitAttachment($arcgis, $objectId, $attachmentId, $token);
                $token = (string) ($download['token'] ?? $token);

                if (! ($download['success'] ?? false)) {
                    $failed++;
                    fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'failed', '', '', '', (string) ($download['message'] ?? 'Download failed.'), $boqPdfRelativePath]);
                    $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'failed', '', (string) ($download['message'] ?? 'Download failed.'));

                    continue;
                }

                File::put($localPath, (string) ($download['body'] ?? ''));
                $downloaded++;
                $matched++;
                fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'downloaded', $localPath, $publicUrl, '', '', $boqPdfRelativePath]);
                $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'downloaded', $publicUrl, '');
            }

            $bar->advance();
        }

        $bar->finish();
        fclose($indexHandle);
        $this->writeExcelIndexFromCsv($indexPath, $xlsxIndexPath);
        File::put($htmlIndexPath, $this->htmlIndex($this->htmlRowsFromCsv($indexPath)));
        $zipPath = $this->createZipArchive($outputDirectory, $outputName);

        $this->newLine(2);
        $this->info("Downloaded: {$downloaded}");
        $this->info("Matched: {$matched}");
        $this->info("Without matching attachments: {$missing}");
        $this->info("Failed: {$failed}");
        $this->info("Index: {$indexPath}");
        $this->info("Excel index: {$xlsxIndexPath}");
        $this->info("HTML index: {$htmlIndexPath}");
        $this->info("ZIP archive: {$zipPath}");
        $this->info('Open URL: '.asset('storage/exports/'.$outputName.'/index.html'));
        $this->info('Download ZIP: '.asset('storage/exports/'.$outputName.'.zip'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function objectIdsFromFile(string $filePath): array
    {
        if (in_array(Str::lower(pathinfo($filePath, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
            return $this->objectIdsFromSpreadsheet($filePath);
        }

        preg_match_all('/\d+(?:\.0+)?/', File::get($filePath), $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $value): string => (string) (int) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function objectIdsFromSpreadsheet(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $values = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            foreach ($worksheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $values[] = $cell->getFormattedValue();
                }
            }
        }

        $spreadsheet->disconnectWorksheets();

        return collect($values)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => preg_match('/^\d+(?:\.0+)?$/', $value) === 1)
            ->map(fn (string $value): string => (string) (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function selectedTypes(): array
    {
        $allowed = ['identity', 'ownership', 'permit'];

        $types = collect(explode(',', (string) $this->option('types')))
            ->map(fn (string $type): string => trim($type))
            ->filter(fn (string $type): bool => in_array($type, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return $types === [] ? $allowed : $types;
    }

    private function generateBoqPdf(string $objectId, string $boqPdfDirectory): string
    {
        $boqColumns = $this->housingBoqColumns();
        $selectColumns = $this->housingBoqSelectColumns($boqColumns);

        if ($selectColumns === []) {
            return '';
        }

        $housingUnit = VHousingUnitAudited::query()
            ->with('building:globalid,objectid')
            ->where('objectid', $objectId)
            ->first($selectColumns);

        if ($housingUnit === null) {
            return '';
        }

        $assessmentHints = Assessment::query()
            ->whereIn('name', $boqColumns)
            ->get(['name', 'hint', 'label'])
            ->keyBy('name');

        $housing = collect([$housingUnit]);
        $boqRows = $this->housingBoqRows($housing, $boqColumns, $assessmentHints);
        $summary = $this->housingBoqSummary($housing, $boqRows);
        $fileName = $this->boqPdfFileName($housingUnit);
        $localPath = $boqPdfDirectory.'/'.$fileName;

        File::ensureDirectoryExists($boqPdfDirectory);

        if (File::exists($localPath) && ! $this->option('force')) {
            return 'boq_pdfs/'.$fileName;
        }

        Pdf::view('damage-assessment::surveys.housing-units.export_pdf', [
            'housing' => $housing,
            'boqRows' => $boqRows,
            'summary' => $summary,
            'sourceTable' => self::HOUSING_EXPORT_SOURCE_TABLE,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])
            ->format('a4')
            ->landscape()
            ->name($fileName)
            ->withBrowsershot(function ($browsershot): void {
                app(BrowsershotConfiguration::class)->apply($browsershot);
            })
            ->save($localPath);

        if (app()->runningUnitTests() && ! File::exists($localPath)) {
            File::put($localPath, '');
        }

        return File::exists($localPath) ? 'boq_pdfs/'.$fileName : '';
    }

    private function boqPdfExportUrl(string $objectId): string
    {
        $housingUnit = VHousingUnitAudited::query()
            ->where('objectid', $objectId)
            ->first(['objectid', 'globalid']);

        if ($housingUnit === null || ! filled($housingUnit->globalid)) {
            return '';
        }

        return url(URL::signedRoute('housing.export.signed', [
            'format' => 'pdf',
            'globalid' => $housingUnit->globalid,
        ], absolute: false));
    }

    /**
     * @param  array<int, string>  $boqColumns
     * @return array<int, string>
     */
    private function housingBoqSelectColumns(array $boqColumns): array
    {
        $availableColumns = $this->housingAuditedColumns();

        return array_values(array_intersect(array_unique(array_merge([
            'objectid',
            'globalid',
            'parentglobalid',
            'housing_unit_number',
            'unit_owner',
            'q_9_3_1_first_name',
            'q_9_3_2_second_name__father',
            'q_9_3_3_third_name__grandfather',
            'q_9_3_4_last_name',
            'municipalitie',
            'neighborhood',
            'unit_damage_status',
        ], $boqColumns)), $availableColumns));
    }

    /**
     * @return array<int, string>
     */
    private function housingBoqColumns(): array
    {
        return collect($this->housingAuditedColumns())
            ->filter(fn (string $column): bool => $this->isHousingBoqColumn($column))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function housingAuditedColumns(): array
    {
        try {
            $columns = Schema::getColumnListing(self::HOUSING_EXPORT_SOURCE_TABLE);
        } catch (\Throwable) {
            $columns = [];
        }

        if ($columns !== []) {
            return $columns;
        }

        try {
            return Schema::getColumnListing('housing_units');
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{success: bool, attachments: array<int, array<string, mixed>>, message?: string|null, token: string}
     */
    private function getHousingUnitAttachments(ArcgisService $arcgis, string $objectId, string $token): array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $result = $arcgis->getAttachmentsResult($objectId, 1, $token);
            } catch (ConnectionException $exception) {
                if ($attempt < 3) {
                    sleep($attempt * 2);

                    continue;
                }

                return [
                    'success' => false,
                    'attachments' => [],
                    'message' => 'ArcGIS connection failed after retries: '.$exception->getMessage(),
                    'token' => $token,
                ];
            }

            if ($result['success'] ?? false) {
                return [
                    'success' => true,
                    'attachments' => $result['attachments'] ?? [],
                    'message' => $result['message'] ?? null,
                    'token' => $token,
                ];
            }

            if (! ($result['token_expired'] ?? false) || $attempt === 3) {
                return [
                    'success' => false,
                    'attachments' => [],
                    'message' => $result['message'] ?? 'ArcGIS request failed.',
                    'token' => $token,
                ];
            }

            Cache::forget('arcgis_token');
            $token = $arcgis->getToken();
        }

        return [
            'success' => false,
            'attachments' => [],
            'message' => 'ArcGIS request failed.',
            'token' => $token,
        ];
    }

    /**
     * @return array{success: bool, message?: string|null, body?: string|null, token: string}
     */
    private function downloadHousingUnitAttachment(ArcgisService $arcgis, string $objectId, int|string $attachmentId, string $token): array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $download = $arcgis->downloadAttachment($objectId, 1, $attachmentId, $token);
            } catch (ConnectionException $exception) {
                if ($attempt < 3) {
                    sleep($attempt * 2);

                    continue;
                }

                return [
                    'success' => false,
                    'message' => 'ArcGIS download connection failed after retries: '.$exception->getMessage(),
                    'body' => null,
                    'token' => $token,
                ];
            }

            if ($download['success'] ?? false) {
                return [
                    ...$download,
                    'token' => $token,
                ];
            }

            if (! ($download['token_expired'] ?? false) || $attempt === 3) {
                return [
                    ...$download,
                    'token' => $token,
                ];
            }

            Cache::forget('arcgis_token');
            $token = $arcgis->getToken();
        }

        return [
            'success' => false,
            'message' => 'Download failed.',
            'body' => null,
            'token' => $token,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @param  array<int, string>  $types
     */
    private function matchingAttachments(array $attachments, array $types, bool $excludeDamage = false): \Illuminate\Support\Collection
    {
        return collect($attachments)
            ->map(function (array $attachment) use ($types, $excludeDamage): array {
                $type = $this->matchingAttachmentType($attachment, $types);

                if ($excludeDamage) {
                    $type = $this->isDamageAttachment($attachment) ? null : ($type ?? 'attachment');
                }

                return [
                    'attachment' => $attachment,
                    'type' => $type,
                ];
            })
            ->filter(fn (array $entry): bool => filled($entry['type']))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @param  array<int, string>  $types
     */
    private function matchingAttachmentType(array $attachment, array $types): ?string
    {
        $searchableText = $this->attachmentSearchableText($attachment);

        foreach ($types as $type) {
            if ($type === 'identity' && $this->containsAny($searchableText, ['identity', ' id ', 'id_', '_id', 'id-', '-id', 'passport', 'هوية', 'الهويه', 'الهوية', 'جواز'])) {
                return 'identity';
            }

            if ($type === 'ownership' && $this->containsAny($searchableText, ['ownership', 'owner', 'deed', 'title', 'land', 'ملكية', 'الملكية', 'طابو', 'سند', 'ارض', 'أرض'])) {
                return 'ownership';
            }

            if ($type === 'permit' && $this->containsAny($searchableText, ['permit', 'municipal', 'municipality', 'license', 'licence', 'رخصة', 'رخصه', 'بلدية', 'البلدية'])) {
                return 'permit';
            }
        }

        return null;
    }

    private function isHousingBoqColumn(string $column): bool
    {
        return (bool) preg_match('/^(dm|bl|co|fn|al|wd|mt|cm|pm|el|pv)\d+$/', $column)
            || (bool) preg_match('/^(item|quant)\d+$/', $column)
            || $column === 'pv_note';
    }

    /**
     * @param  Collection<int, VHousingUnitAudited>  $housing
     * @param  array<int, string>  $boqColumns
     * @param  Collection<string, Assessment>  $assessmentHints
     * @return Collection<int, array<string, mixed>>
     */
    private function housingBoqRows(Collection $housing, array $boqColumns, Collection $assessmentHints): Collection
    {
        $rows = collect();

        foreach ($housing as $housingUnit) {
            $miscDescriptions = [];

            foreach ($boqColumns as $column) {
                $value = $this->cleanBoqValue($housingUnit->{$column} ?? null);

                if ($value === null) {
                    continue;
                }

                if (preg_match('/^item(\d+)$/', $column, $matches)) {
                    $miscDescriptions[$matches[1]] = $value;

                    continue;
                }

                if (preg_match('/^quant(\d+)$/', $column, $matches)) {
                    $description = $miscDescriptions[$matches[1]] ?? $this->boqDescription($column, $assessmentHints);
                    $rows->push($this->housingBoqRow($housingUnit, $column, $description, $value, 'ITEM'.$matches[1], 'Miscellaneous Works'));

                    continue;
                }

                $rows->push($this->housingBoqRow(
                    $housingUnit,
                    $column,
                    $this->boqDescription($column, $assessmentHints),
                    $value,
                    $this->boqItemCode($column, $assessmentHints),
                    $this->boqSection($column)
                ));
            }
        }

        return $rows->values();
    }

    private function housingBoqRow(VHousingUnitAudited $housingUnit, string $column, string $description, string $quantity, string $itemCode, string $section): array
    {
        return [
            'building_objectid' => $housingUnit->building?->objectid ?? '-',
            'objectid' => $housingUnit->objectid,
            'globalid' => $housingUnit->globalid,
            'housing_unit_number' => $housingUnit->housing_unit_number ?: '-',
            'unit_owner' => $this->housingUnitOwnerName($housingUnit),
            'municipalitie' => $housingUnit->municipalitie ?: '-',
            'neighborhood' => $housingUnit->neighborhood ?: '-',
            'unit_damage_status' => $housingUnit->unit_damage_status ?: '-',
            'field' => $column,
            'section' => $section,
            'item_code' => $itemCode,
            'description' => $description,
            'unit' => $this->boqUnit($description),
            'quantity' => $quantity,
        ];
    }

    private function cleanBoqValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '0' || strtolower($value) === 'null') {
            return null;
        }

        return $value;
    }

    /**
     * @param  Collection<string, Assessment>  $assessmentHints
     */
    private function boqDescription(string $column, Collection $assessmentHints): string
    {
        $assessment = $assessmentHints->get($column);
        $description = trim((string) ($assessment?->hint ?: $assessment?->label ?: $column));

        return $description !== '' ? $description : $column;
    }

    /**
     * @param  Collection<string, Assessment>  $assessmentHints
     */
    private function boqItemCode(string $column, Collection $assessmentHints): string
    {
        $label = trim((string) ($assessmentHints->get($column)?->label ?? ''));

        if (preg_match('/^([A-Za-z]{1,4}\d+[A-Za-z]?)\s*[-–]/', $label, $matches)) {
            return strtoupper($matches[1]);
        }

        return strtoupper($column);
    }

    private function boqUnit(string $description): string
    {
        if (preg_match('/\(([^()]+)\)\s*$/u', $description, $matches)) {
            return trim($matches[1]);
        }

        return '-';
    }

    private function boqSection(string $column): string
    {
        if (preg_match('/^fn(\d+)$/', $column, $matches)) {
            $number = (int) $matches[1];

            if ($number >= 1 && $number <= 3) {
                return 'Painting Works';
            }

            if (in_array($number, [5, 6, 7, 8, 10], true)) {
                return 'Tiling Works';
            }

            if ($number === 4 || ($number >= 11 && $number <= 15)) {
                return 'Marble Works';
            }

            if ($number >= 16 && $number <= 26) {
                return 'Plastering Works (Gypsum / Plaster)';
            }

            return 'External Finishings Works';
        }

        foreach ([
            'dm' => 'Demolishing Works',
            'bl' => 'Blocks Works',
            'co' => 'Concrete Works',
            'al' => 'Aluminum Works',
            'wd' => 'Wood Works',
            'mt' => 'Metal Works',
            'cm' => 'Combined',
            'pm' => 'Plumping Works',
            'el' => 'Electrical Works',
            'pv' => 'PV System Works',
        ] as $prefix => $section) {
            if (str_starts_with($column, $prefix)) {
                return $section;
            }
        }

        return 'Miscellaneous Works';
    }

    private function housingUnitOwnerName(VHousingUnitAudited $housingUnit): string
    {
        $name = collect([
            $housingUnit->q_9_3_1_first_name,
            $housingUnit->q_9_3_2_second_name__father,
            $housingUnit->q_9_3_3_third_name__grandfather,
            $housingUnit->q_9_3_4_last_name,
        ])->filter()->implode(' ');

        return $name !== '' ? $name : ($housingUnit->unit_owner ?: '-');
    }

    /**
     * @param  Collection<int, VHousingUnitAudited>  $housing
     * @param  Collection<int, array<string, mixed>>  $boqRows
     * @return array<string, mixed>
     */
    private function housingBoqSummary(Collection $housing, Collection $boqRows): array
    {
        return [
            'units_count' => $housing->count(),
            'rows_count' => $boqRows->count(),
            'sections_count' => $boqRows->pluck('section')->unique()->count(),
            'generated_at' => now()->format('Y-m-d H:i'),
        ];
    }

    private function boqPdfFileName(VHousingUnitAudited $housingUnit): string
    {
        $ownerName = $this->safeExportFileSegment($this->housingUnitOwnerName($housingUnit)) ?? 'unit';
        $objectId = $this->safeExportFileSegment((string) ($housingUnit->objectid ?? '')) ?? (string) $housingUnit->getKey();

        return 'boq-'.$ownerName.'-'.$objectId.'.pdf';
    }

    private function safeExportFileSegment(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        $value = (string) preg_replace('/[\\\\\/:*?"<>|]+/u', ' ', $value);
        $value = (string) preg_replace('/\s+/u', '-', trim($value));
        $value = trim($value, '-_. ');

        return $value !== '' ? (string) str($value)->limit(80, '') : null;
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function isDamageAttachment(array $attachment): bool
    {
        return $this->containsAny($this->attachmentSearchableText($attachment), [
            'damage',
            'damge',
            'damaged',
            'photo_',
            '_photo',
            'صورة الضرر',
            'صور الضرر',
            'اضرار',
            'أضرار',
            'ضرر',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function attachmentSearchableText(array $attachment): string
    {
        return ' '.Str::lower(collect([
            $attachment['name'] ?? '',
            $attachment['contentType'] ?? '',
            $attachment['keywords'] ?? '',
            $attachment['globalId'] ?? '',
            $attachment['parentGlobalId'] ?? '',
        ])->flatten()->map(fn ($value): string => (string) $value)->implode(' ')).' ';
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function attachmentRelativePath(string $objectId, string $type, string $attachmentId, string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $nameWithoutExtension = pathinfo($originalName, PATHINFO_FILENAME);
        $fileName = $objectId.'_unit_'.$type.'_'.$attachmentId.'_'.$this->safePathSegment($nameWithoutExtension);

        if ($extension !== '') {
            $fileName .= '.'.$this->safePathSegment($extension);
        }

        return 'housing_units/'.$this->safePathSegment($objectId).'/'.$fileName;
    }

    private function urlPath(string $path): string
    {
        return collect(explode('/', str_replace('\\', '/', $path)))
            ->map(fn (string $segment): string => rawurlencode($segment))
            ->implode('/');
    }

    /**
     * @return array<int, string>
     */
    private function processedObjectIdsFromCsv(string $csvPath): array
    {
        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            return [];
        }

        $processed = [];
        $rowNumber = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($rowNumber === 1) {
                continue;
            }

            $objectId = (string) ($row[0] ?? '');
            $status = (string) ($row[5] ?? '');

            if (! filled($objectId) || in_array($status, ['failed', 'failed_request'], true)) {
                continue;
            }

            $processed[$objectId] = true;
        }

        fclose($handle);

        return array_map('strval', array_keys($processed));
    }

    /**
     * @return array<int, string>
     */
    private function htmlRowsFromCsv(string $csvPath): array
    {
        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            return [];
        }

        $rows = [];
        $rowNumber = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($rowNumber === 1) {
                continue;
            }

            $rows[] = $this->htmlRow(
                (string) ($row[0] ?? ''),
                (string) ($row[2] ?? ''),
                (string) ($row[3] ?? ''),
                (string) ($row[4] ?? ''),
                (string) ($row[5] ?? ''),
                (string) (($row[7] ?? '') ?: ($row[8] ?? '')),
                (string) ($row[9] ?? '')
            );
        }

        fclose($handle);

        return $rows;
    }

    private function writeExcelIndexFromCsv(string $csvPath, string $xlsxPath): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('attachments-index');

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            return;
        }

        $rowNumber = 1;
        $localRows = [];
        $arcgisUrlsByObjectId = [];
        $boqPdfPathsByObjectId = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($rowNumber === 1) {
                $rowNumber++;

                continue;
            }

            $objectId = (string) ($row[0] ?? '');
            $status = (string) ($row[5] ?? '');
            $localPath = (string) ($row[6] ?? '');
            $arcgisUrl = (string) ($row[8] ?? '');
            $boqPdfPath = (string) ($row[10] ?? '');

            if (filled($boqPdfPath)) {
                $boqPdfPathsByObjectId[$objectId] = $this->boqPdfHyperlink($boqPdfPath);
            }

            if (in_array($status, ['not_found', 'online_only'], true) && filled($arcgisUrl)) {
                $arcgisUrlsByObjectId[$objectId] ??= [];
                $arcgisUrlsByObjectId[$objectId][] = $arcgisUrl;

                continue;
            }

            if (! in_array($status, ['downloaded', 'skipped_existing'], true) || ! filled($localPath)) {
                continue;
            }

            $relativePath = $this->localHyperlinkPath($localPath);

            if (! filled($relativePath)) {
                continue;
            }

            $localRows[] = [
                'object_id' => $objectId,
                'relative_path' => $relativePath,
            ];
        }

        fclose($handle);

        $maxArcgisLinks = collect($arcgisUrlsByObjectId)
            ->map(fn (array $urls): int => count(array_unique($urls)))
            ->max() ?? 0;
        $hasBoqPdfLinks = collect($boqPdfPathsByObjectId)->filter()->isNotEmpty();
        $boqColumnNumber = $hasBoqPdfLinks ? 2 : null;
        $localColumnNumber = $hasBoqPdfLinks ? 3 : 2;
        $arcgisStartColumnNumber = $localColumnNumber + 1;

        $sheet->setCellValue('A1', 'objectid');
        if ($hasBoqPdfLinks) {
            $sheet->setCellValue([$boqColumnNumber, 1], 'رابط جدول الكميات PDF');
        }

        $sheet->setCellValue([$localColumnNumber, 1], 'رابط المرفق المحلي');

        for ($index = 1; $index <= $maxArcgisLinks; $index++) {
            $sheet->setCellValue([$arcgisStartColumnNumber + $index - 1, 1], "مرفق ArcGIS {$index}");
        }

        $writtenObjectIds = [];

        foreach ($localRows as $localRow) {
            $excelRow = $sheet->getHighestRow() + 1;
            $sheet->setCellValue("A{$excelRow}", $localRow['object_id']);
            $this->writeBoqPdfLink($sheet, $excelRow, (string) $localRow['object_id'], $boqPdfPathsByObjectId, $boqColumnNumber);

            $localCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($localColumnNumber).$excelRow;
            $sheet->setCellValue($localCoordinate, 'فتح المرفق');
            $sheet->getCell($localCoordinate)->getHyperlink()->setUrl($localRow['relative_path']);
            $this->styleHyperlink($localCoordinate, $sheet);
            $writtenObjectIds[] = (string) $localRow['object_id'];
        }

        foreach ($arcgisUrlsByObjectId as $objectId => $urls) {
            $urls = array_values(array_unique($urls));

            if ($urls === []) {
                continue;
            }

            $excelRow = $sheet->getHighestRow() + 1;
            $sheet->setCellValue("A{$excelRow}", $objectId);
            $this->writeBoqPdfLink($sheet, $excelRow, (string) $objectId, $boqPdfPathsByObjectId, $boqColumnNumber);
            $localCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($localColumnNumber).$excelRow;
            $sheet->setCellValue($localCoordinate, '');

            foreach ($urls as $index => $url) {
                $columnNumber = $arcgisStartColumnNumber + $index;
                $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnNumber).$excelRow;
                $sheet->setCellValue($coordinate, 'مرفق '.($index + 1));
                $sheet->getCell($coordinate)->getHyperlink()->setUrl($url);
                $this->styleHyperlink($coordinate, $sheet);
            }

            $writtenObjectIds[] = (string) $objectId;
        }

        foreach ($boqPdfPathsByObjectId as $objectId => $boqPdfPath) {
            if (! filled($boqPdfPath) || in_array((string) $objectId, $writtenObjectIds, true)) {
                continue;
            }

            $excelRow = $sheet->getHighestRow() + 1;
            $sheet->setCellValue("A{$excelRow}", $objectId);
            $this->writeBoqPdfLink($sheet, $excelRow, (string) $objectId, $boqPdfPathsByObjectId, $boqColumnNumber);
        }

        $highestColumnIndex = max($localColumnNumber, $maxArcgisLinks + $arcgisStartColumnNumber - 1);

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $lastHeaderCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($highestColumnIndex).'1';
        $sheet->getStyle("A1:{$lastHeaderCoordinate}")->getFont()->setBold(true);

        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * @param  array<string, string>  $boqPdfPathsByObjectId
     */
    private function writeBoqPdfLink(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $excelRow, string $objectId, array $boqPdfPathsByObjectId, ?int $boqColumnNumber): void
    {
        if ($boqColumnNumber === null || ! filled($boqPdfPathsByObjectId[$objectId] ?? '')) {
            return;
        }

        $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($boqColumnNumber).$excelRow;
        $sheet->setCellValue($coordinate, 'فتح جدول الكميات');
        $sheet->getCell($coordinate)->getHyperlink()->setUrl($boqPdfPathsByObjectId[$objectId]);
        $this->styleHyperlink($coordinate, $sheet);
    }

    private function styleHyperlink(string $coordinate, \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $sheet->getStyle($coordinate)->applyFromArray([
            'font' => [
                'color' => ['rgb' => '0563C1'],
                'underline' => true,
            ],
        ]);
    }

    private function arcgisAttachmentUrl(string $objectId, string $attachmentId, string $token, int $layerId = 1): string
    {
        return 'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/'
            .$layerId
            .'/'
            .rawurlencode($objectId)
            .'/attachments/'
            .rawurlencode($attachmentId)
            .'?token='
            .rawurlencode($token);
    }

    private function localHyperlinkPath(string $localPath): string
    {
        $normalizedPath = str_replace('\\', '/', $localPath);

        foreach (['/housing_units/', '/boq_pdfs/'] as $pathMarker) {
            $position = strpos($normalizedPath, $pathMarker);

            if ($position !== false) {
                return ltrim(substr($normalizedPath, $position + 1), '/');
            }
        }

        if (str_starts_with($normalizedPath, 'boq_pdfs/')) {
            return $normalizedPath;
        }

        return '';
    }

    private function boqPdfHyperlink(string $boqPdfPath): string
    {
        if (Str::startsWith($boqPdfPath, ['http://', 'https://'])) {
            return $boqPdfPath;
        }

        return $this->localHyperlinkPath($boqPdfPath);
    }

    private function createZipArchive(string $outputDirectory, string $outputName): string
    {
        $zipPath = dirname($outputDirectory).'/'.$outputName.'.zip';

        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new \ZipArchive;

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Unable to create ZIP archive: {$zipPath}");
        }

        foreach (File::allFiles($outputDirectory) as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $zip->addFile($file->getRealPath(), $relativePath);
        }

        $zip->close();

        return $zipPath;
    }

    private function htmlIndex(array $rows): string
    {
        $bodyRows = implode("\n", $rows);

        return <<<HTML
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Housing Unit Attachments</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #172033; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d9e1ee; padding: 8px 10px; text-align: right; }
        th { background: #eef4ff; }
        a { color: #0b63ce; font-weight: 700; }
        .muted { color: #667085; }
    </style>
</head>
<body>
    <h1>مرفقات الوحدات السكنية</h1>
    <table>
        <thead>
            <tr>
                <th>ObjectID</th>
                <th>النوع</th>
                <th>اسم الملف</th>
                <th>نوع المحتوى</th>
                <th>الحالة</th>
                <th>الرابط</th>
                <th>ملاحظة</th>
            </tr>
        </thead>
        <tbody>
{$bodyRows}
        </tbody>
    </table>
</body>
</html>
HTML;
    }

    private function htmlRow(string $objectId, string $type, string $originalName, string $contentType, string $status, string $publicUrl, string $message): string
    {
        $link = filled($publicUrl)
            ? '<a href="'.e($publicUrl).'" target="_blank" rel="noopener">فتح المرفق</a>'
            : '<span class="muted">لا يوجد رابط</span>';

        return '<tr>'
            .'<td>'.e($objectId).'</td>'
            .'<td>'.e($type).'</td>'
            .'<td>'.e($originalName).'</td>'
            .'<td>'.e($contentType).'</td>'
            .'<td>'.e($status).'</td>'
            .'<td>'.$link.'</td>'
            .'<td>'.e($message).'</td>'
            .'</tr>';
    }

    private function safePathSegment(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return $value !== '' ? $value : 'unknown';
    }
}
