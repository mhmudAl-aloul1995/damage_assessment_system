<?php

namespace App\Console\Commands;

use App\services\ArcgisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
        {file : Text/CSV file containing housing unit ObjectIDs}
        {--output= : Output directory relative to storage/app/public/exports}
        {--types=ownership,permit : Comma separated attachment types: ownership,permit}
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
            'message',
        ]);

        $htmlRows = [];
        $this->info('ObjectIDs: '.count($objectIds));
        $this->info('Types: '.implode(', ', $types));
        $this->info("Output: {$outputDirectory}");

        $token = $arcgis->getToken();
        $downloaded = 0;
        $matched = 0;
        $missing = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar(count($objectIds));
        $bar->start();

        foreach ($objectIds as $objectId) {
            $attachments = $arcgis->getAttachments($objectId, 1, $token);
            $matchingAttachments = collect($attachments)
                ->map(fn (array $attachment): array => [
                    'attachment' => $attachment,
                    'type' => $this->matchingAttachmentType($attachment, $types),
                ])
                ->filter(fn (array $entry): bool => filled($entry['type']))
                ->values();

            if ($matchingAttachments->isEmpty()) {
                $missing++;
                fputcsv($indexHandle, [$objectId, '', '', '', '', 'not_found', '', '', 'No ownership or permit attachments matched.']);
                $htmlRows[] = $this->htmlRow($objectId, '', '', '', 'not_found', '', 'No ownership or permit attachments matched.');
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
                    fputcsv($indexHandle, [$objectId, '', $matchedType, $originalName, $contentType, 'failed', '', '', 'Missing attachment id.']);
                    $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'failed', '', 'Missing attachment id.');

                    continue;
                }

                $relativePath = $this->attachmentRelativePath($objectId, $matchedType, (string) $attachmentId, $originalName);
                $localPath = $outputDirectory.'/'.$relativePath;
                $publicUrl = asset('storage/exports/'.$outputName.'/'.$this->urlPath($relativePath));
                File::ensureDirectoryExists(dirname($localPath));

                if (File::exists($localPath) && ! $this->option('force')) {
                    $matched++;
                    fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'skipped_existing', $localPath, $publicUrl, 'File already exists.']);
                    $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'skipped_existing', $publicUrl, 'File already exists.');

                    continue;
                }

                $download = $arcgis->downloadAttachment($objectId, 1, $attachmentId, $token);

                if (! ($download['success'] ?? false)) {
                    $failed++;
                    fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'failed', '', '', (string) ($download['message'] ?? 'Download failed.')]);
                    $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'failed', '', (string) ($download['message'] ?? 'Download failed.'));

                    continue;
                }

                File::put($localPath, (string) ($download['body'] ?? ''));
                $downloaded++;
                $matched++;
                fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'downloaded', $localPath, $publicUrl, '']);
                $htmlRows[] = $this->htmlRow($objectId, $matchedType, $originalName, $contentType, 'downloaded', $publicUrl, '');
            }

            $bar->advance();
        }

        $bar->finish();
        fclose($indexHandle);
        $this->writeExcelIndexFromCsv($indexPath, $xlsxIndexPath);
        File::put($htmlIndexPath, $this->htmlIndex($htmlRows));

        $this->newLine(2);
        $this->info("Downloaded: {$downloaded}");
        $this->info("Matched: {$matched}");
        $this->info("Without matching attachments: {$missing}");
        $this->info("Failed: {$failed}");
        $this->info("Index: {$indexPath}");
        $this->info("Excel index: {$xlsxIndexPath}");
        $this->info("HTML index: {$htmlIndexPath}");
        $this->info('Open URL: '.asset('storage/exports/'.$outputName.'/index.html'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function objectIdsFromFile(string $filePath): array
    {
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
    private function selectedTypes(): array
    {
        $allowed = ['ownership', 'permit'];

        $types = collect(explode(',', (string) $this->option('types')))
            ->map(fn (string $type): string => trim($type))
            ->filter(fn (string $type): bool => in_array($type, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return $types === [] ? $allowed : $types;
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @param  array<int, string>  $types
     */
    private function matchingAttachmentType(array $attachment, array $types): ?string
    {
        $searchableText = $this->attachmentSearchableText($attachment);

        foreach ($types as $type) {
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
        $publicUrlColumn = 8;

        while (($row = fgetcsv($handle)) !== false) {
            foreach ($row as $index => $value) {
                $columnNumber = $index + 1;
                $cell = $sheet->getCell([$columnNumber, $rowNumber]);

                if ($rowNumber > 1 && $columnNumber === $publicUrlColumn && filled($value)) {
                    $cell->setValue('فتح المرفق');
                    $cell->getHyperlink()->setUrl((string) $value);
                    $sheet->getStyle([$columnNumber, $rowNumber])->applyFromArray([
                        'font' => [
                            'color' => ['rgb' => '0563C1'],
                            'underline' => true,
                        ],
                    ]);

                    continue;
                }

                $cell->setValue($value);
            }

            $rowNumber++;
        }

        fclose($handle);

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();
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
