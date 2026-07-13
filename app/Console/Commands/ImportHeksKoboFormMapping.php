<?php

namespace App\Console\Commands;

use App\Modules\Heks\Models\HeksKoboFieldMapping;
use App\Modules\Heks\Services\HeksKoboServiceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportHeksKoboFormMapping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heks:kobo-import-form-mapping
        {service : HEKS service name, for example heks-main}
        {asset? : KoboToolbox asset UID. If omitted, the configured asset for the service is used}
        {--dry-run : Read the Kobo form and report what would be imported without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import HEKS Kobo field display labels directly from the KoboToolbox form definition';

    public function handle(HeksKoboServiceRegistry $services): int
    {
        $service = (string) $this->argument('service');
        $canonicalService = $services->canonical($service);

        if ($canonicalService === null) {
            $this->components->error('Unsupported HEKS Kobo service.');

            return self::FAILURE;
        }

        $asset = (string) ($this->argument('asset') ?: config("heks_kobo.services.{$canonicalService}.asset_uid", ''));
        $mappingService = $this->mappingServiceName($service, $canonicalService);
        $token = (string) config('services.kobotoolbox.token', '');
        $tableName = $services->wideTable($canonicalService);

        if ($asset === '') {
            $this->components->error('Kobo asset UID is required. Pass it as the second argument or configure it in .env.');

            return self::FAILURE;
        }

        if ($token === '') {
            $this->components->error('KOBOTOOLBOX_TOKEN is not configured.');

            return self::FAILURE;
        }

        if ($tableName === null) {
            $this->components->error('The HEKS service does not have a configured wide table.');

            return self::FAILURE;
        }

        $response = Http::timeout((int) config('services.kobotoolbox.timeout', 60))
            ->withHeader('Authorization', "Token {$token}")
            ->acceptJson()
            ->get("https://kf.kobotoolbox.org/api/v2/assets/{$asset}/?format=json");

        if (! $response->successful()) {
            $this->components->error("Kobo form request failed with status {$response->status()}.");

            if ($response->status() === 404) {
                $this->components->warn('Check that the asset UID belongs to the same Kobo account as KOBOTOOLBOX_TOKEN, or share the project with that account.');
            }

            return self::FAILURE;
        }

        $survey = Arr::get($response->json(), 'content.survey', []);

        if (! is_array($survey) || $survey === []) {
            $this->components->error('Kobo response did not include content.survey rows.');

            return self::FAILURE;
        }

        [$created, $updated, $skipped] = $this->importSurvey($mappingService, $tableName, $survey);

        $this->components->info(
            $this->option('dry-run')
                ? "HEKS Kobo form mapping dry run. New: {$created}, existing: {$updated}, skipped: {$skipped}."
                : "HEKS Kobo form mapping imported. Created: {$created}, updated: {$updated}, skipped: {$skipped}."
        );

        return self::SUCCESS;
    }

    private function mappingServiceName(string $service, string $canonicalService): string
    {
        if (str_contains($service, '-')) {
            return $service;
        }

        $aliases = (array) config("heks_kobo.services.{$canonicalService}.aliases", []);

        return is_string($aliases[0] ?? null) ? $aliases[0] : $canonicalService;
    }

    /**
     * @param  array<int, mixed>  $survey
     * @return array{0: int, 1: int, 2: int}
     */
    private function importSurvey(string $service, string $tableName, array $survey): array
    {
        $groupStack = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($survey as $row) {
            if (! is_array($row)) {
                $skipped++;

                continue;
            }

            $type = strtolower(trim((string) Arr::get($row, 'type', '')));
            $name = trim((string) Arr::get($row, 'name', ''));

            if ($this->startsGroup($type)) {
                if ($name !== '') {
                    $groupStack[] = $name;
                }

                continue;
            }

            if ($this->endsGroup($type)) {
                array_pop($groupStack);

                continue;
            }

            if ($name === '' || $this->isSkippedQuestionType($type)) {
                $skipped++;

                continue;
            }

            $label = $this->displayLabel($row);

            if ($label === '') {
                $skipped++;

                continue;
            }

            $fields = $this->fieldKeys($name, $groupStack);

            foreach ($fields as $field) {
                $mapping = HeksKoboFieldMapping::query()->firstOrNew([
                    'service_name' => $service,
                    'table_name' => $tableName,
                    'kobo_field' => $field,
                ]);
                $wasRecentlyCreated = ! $mapping->exists;

                if (! $this->option('dry-run')) {
                    $mapping->fill([
                        'column_name' => $mapping->column_name ?: $this->uniqueColumnName($service, $tableName, $field),
                        'display_label' => $label,
                        'data_type' => $type ?: null,
                        'mapping_status' => 'kobo_form',
                        'confidence' => 'high',
                        'notes' => $field === $name ? null : 'Imported from nested Kobo form path.',
                    ])->save();
                }

                $wasRecentlyCreated ? $created++ : $updated++;
            }
        }

        return [$created, $updated, $skipped];
    }

    private function startsGroup(string $type): bool
    {
        return str_starts_with($type, 'begin_group')
            || str_starts_with($type, 'begin repeat')
            || str_starts_with($type, 'begin_repeat');
    }

    private function endsGroup(string $type): bool
    {
        return str_starts_with($type, 'end_group')
            || str_starts_with($type, 'end repeat')
            || str_starts_with($type, 'end_repeat');
    }

    private function isSkippedQuestionType(string $type): bool
    {
        return $type === ''
            || str_starts_with($type, 'calculate')
            || str_starts_with($type, 'note')
            || str_starts_with($type, 'acknowledge')
            || in_array($type, ['start', 'end', 'today', 'deviceid', 'username', 'audit'], true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function displayLabel(array $row): string
    {
        $label = Arr::get($row, 'label');

        if (is_array($label)) {
            return $this->localizedLabel($label);
        }

        if (is_string($label) && trim($label) !== '') {
            return trim($label);
        }

        foreach ($row as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'label')) {
                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value)) {
                $localized = $this->localizedLabel($value);

                if ($localized !== '') {
                    return $localized;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $labels
     */
    private function localizedLabel(array $labels): string
    {
        foreach ($labels as $language => $label) {
            $language = strtolower((string) $language);

            if (! is_string($label) || trim($label) === '') {
                continue;
            }

            if (str_contains($language, 'ar') || str_contains($language, 'arab')) {
                return trim($label);
            }
        }

        foreach ($labels as $label) {
            if (is_string($label) && trim($label) !== '') {
                return trim($label);
            }
        }

        return '';
    }

    /**
     * @param  array<int, string>  $groupStack
     * @return array<int, string>
     */
    private function fieldKeys(string $name, array $groupStack): array
    {
        $fieldKeys = [$name];

        if ($groupStack !== [] && ! str_contains($name, '/')) {
            $fieldKeys[] = implode('/', [...$groupStack, $name]);
        }

        return array_values(array_unique($fieldKeys));
    }

    private function columnName(string $field): string
    {
        $column = Str::of($field)
            ->replace(['/', '-', '.', ' ', ':'], '_')
            ->replaceMatches('/[^A-Za-z0-9_]+/', '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->lower()
            ->toString();

        if ($column === '') {
            $column = 'field_'.substr(sha1($field), 0, 12);
        }

        if (is_numeric($column[0])) {
            $column = 'field_'.$column;
        }

        if (strlen($column) > 58) {
            $column = substr($column, 0, 45).'_'.substr(sha1($field), 0, 12);
        }

        return $column;
    }

    private function uniqueColumnName(string $service, string $tableName, string $field): string
    {
        $base = $this->columnName($field);
        $column = $base;
        $attempt = 0;

        while (HeksKoboFieldMapping::query()
            ->where('service_name', $service)
            ->where('table_name', $tableName)
            ->where('column_name', $column)
            ->exists()) {
            $attempt++;
            $suffix = substr(sha1($field.$attempt), 0, 8);
            $column = substr($base, 0, 55).'_'.$suffix;
        }

        return $column;
    }
}
