<?php

namespace App\Console\Commands;

use App\services\ArcgisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
        $outputName = $this->option('output') ?: 'housing_unit_attachments_'.now()->format('Ymd_His');
        $outputDirectory = storage_path('app/public/exports/'.$this->safePathSegment((string) $outputName));

        File::ensureDirectoryExists($outputDirectory);

        $indexPath = $outputDirectory.'/attachments-index.csv';
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
            'message',
        ]);

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
                fputcsv($indexHandle, [$objectId, '', '', '', '', 'not_found', '', 'No ownership or permit attachments matched.']);
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
                    fputcsv($indexHandle, [$objectId, '', $matchedType, $originalName, $contentType, 'failed', '', 'Missing attachment id.']);

                    continue;
                }

                $localPath = $this->attachmentLocalPath($outputDirectory, $objectId, $matchedType, (string) $attachmentId, $originalName);
                File::ensureDirectoryExists(dirname($localPath));

                if (File::exists($localPath) && ! $this->option('force')) {
                    $matched++;
                    fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'skipped_existing', $localPath, 'File already exists.']);

                    continue;
                }

                $download = $arcgis->downloadAttachment($objectId, 1, $attachmentId, $token);

                if (! ($download['success'] ?? false)) {
                    $failed++;
                    fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'failed', '', (string) ($download['message'] ?? 'Download failed.')]);

                    continue;
                }

                File::put($localPath, (string) ($download['body'] ?? ''));
                $downloaded++;
                $matched++;
                fputcsv($indexHandle, [$objectId, $attachmentId, $matchedType, $originalName, $contentType, 'downloaded', $localPath, '']);
            }

            $bar->advance();
        }

        $bar->finish();
        fclose($indexHandle);

        $this->newLine(2);
        $this->info("Downloaded: {$downloaded}");
        $this->info("Matched: {$matched}");
        $this->info("Without matching attachments: {$missing}");
        $this->info("Failed: {$failed}");
        $this->info("Index: {$indexPath}");

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

    private function attachmentLocalPath(string $outputDirectory, string $objectId, string $type, string $attachmentId, string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $nameWithoutExtension = pathinfo($originalName, PATHINFO_FILENAME);
        $fileName = $objectId.'_unit_'.$type.'_'.$attachmentId.'_'.$this->safePathSegment($nameWithoutExtension);

        if ($extension !== '') {
            $fileName .= '.'.$this->safePathSegment($extension);
        }

        return $outputDirectory.'/housing_units/'.$this->safePathSegment($objectId).'/'.$fileName;
    }

    private function safePathSegment(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return $value !== '' ? $value : 'unknown';
    }
}
