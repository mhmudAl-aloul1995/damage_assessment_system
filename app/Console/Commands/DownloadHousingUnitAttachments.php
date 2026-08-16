<?php

namespace App\Console\Commands;

use App\services\ArcgisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DownloadHousingUnitAttachments extends Command
{
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
        {--limit= : Process only the first N ObjectIDs}
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
        $outputName = $this->safePathSegment((string) ($this->option('output') ?: 'housing_unit_attachments_'.now()->format('Ymd_His')));
        $outputDirectory = storage_path('app/public/exports/'.$outputName);

        File::ensureDirectoryExists($outputDirectory);

        $indexPath = $outputDirectory.'/attachments-index.csv';
        $xlsxIndexPath = $outputDirectory.'/attachments-index.xlsx';
        $htmlIndexPath = $outputDirectory.'/index.html';
        $indexHandle = fopen($indexPath, 'w');

        if ($indexHandle === false) {
            $this->error("Unable to create index file: {$indexPath}");

            return self::FAILURE;
        }

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
        ]);

        $htmlRows = [];
        $this->info('ObjectIDs: '.count($objectIds));
        $this->info($excludeDamage ? 'Mode: all attachments except damage photos' : 'Types: '.implode(', ', $types));
        $this->info("Output: {$outputDirectory}");

        $token = $arcgis->getToken();
        $downloaded = 0;
        $matched = 0;
        $missing = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar(count($objectIds));
        $bar->start();

        foreach ($objectIds as $objectId) {
            $attachmentsResult = $this->getHousingUnitAttachments($arcgis, $objectId, $token);

            if (! ($attachmentsResult['success'] ?? false)) {
                $failed++;
                fputcsv($indexHandle, [$objectId, '', '', '', '', 'failed_request', '', '', '', (string) ($attachmentsResult['message'] ?? 'ArcGIS request failed.')]);
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
                    fputcsv($indexHandle, [$objectId, '', '', '', '', 'not_found', '', '', '', 'No matching attachments were found.']);
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
                    fputcsv($indexHandle, [$objectId, '', $matchedType, $originalName, $contentType, 'failed', '', '', '', 'Missing attachment id.']);
                    $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'failed', '', 'Missing attachment id.');

                    continue;
                }

                $relativePath = $this->attachmentRelativePath($objectId, $matchedType, (string) $attachmentId, $originalName);
                $localPath = $outputDirectory.'/'.$relativePath;
                $publicUrl = asset('storage/exports/'.$outputName.'/'.$this->urlPath($relativePath));
                File::ensureDirectoryExists(dirname($localPath));

                if (File::exists($localPath) && ! $this->option('force')) {
                    $matched++;
                    fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'skipped_existing', $localPath, $publicUrl, '', 'File already exists.']);
                    $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'skipped_existing', $publicUrl, 'File already exists.');

                    continue;
                }

                $download = $this->downloadHousingUnitAttachment($arcgis, $objectId, $attachmentId, $token);
                $token = (string) ($download['token'] ?? $token);

                if (! ($download['success'] ?? false)) {
                    $failed++;
                    fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'failed', '', '', '', (string) ($download['message'] ?? 'Download failed.')]);
                    $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'failed', '', (string) ($download['message'] ?? 'Download failed.'));

                    continue;
                }

                File::put($localPath, (string) ($download['body'] ?? ''));
                $downloaded++;
                $matched++;
                fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'downloaded', $localPath, $publicUrl, '', '']);
                $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'downloaded', $publicUrl, '');
            }

            $bar->advance();
        }

        $bar->finish();
        fclose($indexHandle);
        $this->writeExcelIndexFromCsv($indexPath, $xlsxIndexPath);
        File::put($htmlIndexPath, $this->htmlIndex($htmlRows));
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

    /**
     * @return array{success: bool, attachments: array<int, array<string, mixed>>, message?: string|null, token: string}
     */
    private function getHousingUnitAttachments(ArcgisService $arcgis, string $objectId, string $token): array
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $result = $arcgis->getAttachmentsResult($objectId, 1, $token);

            if ($result['success'] ?? false) {
                return [
                    'success' => true,
                    'attachments' => $result['attachments'] ?? [],
                    'message' => $result['message'] ?? null,
                    'token' => $token,
                ];
            }

            if (! ($result['token_expired'] ?? false) || $attempt === 2) {
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
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $download = $arcgis->downloadAttachment($objectId, 1, $attachmentId, $token);

            if ($download['success'] ?? false) {
                return [
                    ...$download,
                    'token' => $token,
                ];
            }

            if (! ($download['token_expired'] ?? false) || $attempt === 2) {
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

        while (($row = fgetcsv($handle)) !== false) {
            if ($rowNumber === 1) {
                $rowNumber++;

                continue;
            }

            $objectId = (string) ($row[0] ?? '');
            $status = (string) ($row[5] ?? '');
            $localPath = (string) ($row[6] ?? '');
            $arcgisUrl = (string) ($row[8] ?? '');

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

        $sheet->setCellValue('A1', 'objectid');
        $sheet->setCellValue('B1', 'رابط المرفق المحلي');

        for ($index = 1; $index <= $maxArcgisLinks; $index++) {
            $sheet->setCellValue([$index + 2, 1], "مرفق ArcGIS {$index}");
        }

        foreach ($localRows as $localRow) {
            $excelRow = $sheet->getHighestRow() + 1;
            $sheet->setCellValue("A{$excelRow}", $localRow['object_id']);
            $sheet->setCellValue("B{$excelRow}", 'فتح المرفق');
            $sheet->getCell("B{$excelRow}")->getHyperlink()->setUrl($localRow['relative_path']);
            $this->styleHyperlink("B{$excelRow}", $sheet);
        }

        foreach ($arcgisUrlsByObjectId as $objectId => $urls) {
            $urls = array_values(array_unique($urls));

            if ($urls === []) {
                continue;
            }

            $excelRow = $sheet->getHighestRow() + 1;
            $sheet->setCellValue("A{$excelRow}", $objectId);
            $sheet->setCellValue("B{$excelRow}", '');

            foreach ($urls as $index => $url) {
                $columnNumber = $index + 3;
                $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnNumber).$excelRow;
                $sheet->setCellValue($coordinate, 'مرفق '.($index + 1));
                $sheet->getCell($coordinate)->getHyperlink()->setUrl($url);
                $this->styleHyperlink($coordinate, $sheet);
            }
        }

        $highestColumnIndex = max(2, $maxArcgisLinks + 2);

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $lastHeaderCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($highestColumnIndex).'1';
        $sheet->getStyle("A1:{$lastHeaderCoordinate}")->getFont()->setBold(true);

        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();
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
        $position = strpos($normalizedPath, '/housing_units/');

        if ($position === false) {
            return '';
        }

        return ltrim(substr($normalizedPath, $position + 1), '/');
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
