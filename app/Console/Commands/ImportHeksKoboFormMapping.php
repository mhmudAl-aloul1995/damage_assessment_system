<?php

namespace App\Console\Commands;

use App\Modules\Heks\Models\HeksKoboFieldMapping;
use App\Modules\Heks\Services\HeksKoboChoiceSyncService;
use App\Modules\Heks\Services\HeksKoboServiceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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
        {--xlsform= : Read survey and choices sheets from a local XLSForm file instead of Kobo API}
        {--dry-run : Read the Kobo form and report what would be imported without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import HEKS Kobo field display labels directly from the KoboToolbox form definition';

    public function handle(HeksKoboServiceRegistry $services, HeksKoboChoiceSyncService $choiceSyncService): int
    {
        $service = (string) $this->argument('service');
        $canonicalService = $services->canonical($service);

        if ($canonicalService === null) {
            $this->components->error('Unsupported HEKS Kobo service.');

            return self::FAILURE;
        }

        $xlsform = (string) ($this->option('xlsform') ?: '');
        $asset = (string) ($this->argument('asset') ?: config("heks_kobo.services.{$canonicalService}.asset_uid", ''));
        $mappingService = $this->mappingServiceName($service, $canonicalService);
        $token = (string) config('services.kobotoolbox.token', '');
        $tableName = $services->wideTable($canonicalService);

        if ($asset === '' && $xlsform === '') {
            $this->components->error('Kobo asset UID is required. Pass it as the second argument or configure it in .env.');

            return self::FAILURE;
        }

        if ($token === '' && $xlsform === '') {
            $this->components->error('KOBOTOOLBOX_TOKEN is not configured.');

            return self::FAILURE;
        }

        if ($tableName === null) {
            $this->components->error('The HEKS service does not have a configured wide table.');

            return self::FAILURE;
        }

        $body = $xlsform !== ''
            ? $this->readXlsForm($xlsform)
            : $this->readKoboForm($asset, $token);

        if ($body === null) {
            return self::FAILURE;
        }

        $survey = Arr::get($body, 'content.survey', []);
        $choices = Arr::get($body, 'content.choices', []);

        if (! is_array($survey) || $survey === []) {
            $this->components->error('Kobo response did not include content.survey rows.');

            return self::FAILURE;
        }

        [$created, $updated, $skipped] = $this->importSurvey(
            $mappingService,
            $tableName,
            $survey,
            is_array($choices) ? $this->choiceLabelsByList($choices) : [],
            $choiceSyncService
        );

        $choiceStats = $choiceSyncService->sync(
            $mappingService,
            $survey,
            is_array($choices) ? $choices : [],
            is_string($body['version_id'] ?? null) ? $body['version_id'] : null,
            (bool) $this->option('dry-run')
        );

        $this->components->info(
            $this->option('dry-run')
                ? "HEKS Kobo form mapping dry run. New: {$created}, existing: {$updated}, skipped: {$skipped}."
                : "HEKS Kobo form mapping imported. Created: {$created}, updated: {$updated}, skipped: {$skipped}."
        );
        $this->components->info("HEKS Kobo choices synced. Select one: {$choiceStats['select_one']}, select multiple: {$choiceStats['select_multiple']}, choices: {$choiceStats['choices']}, inactive: {$choiceStats['inactive']}.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readKoboForm(string $asset, string $token): ?array
    {
        $response = Http::timeout((int) config('services.kobotoolbox.timeout', 60))
            ->withHeader('Authorization', "Token {$token}")
            ->acceptJson()
            ->get("https://kf.kobotoolbox.org/api/v2/assets/{$asset}/?format=json");

        if (! $response->successful()) {
            $this->components->error("Kobo form request failed with status {$response->status()}.");

            if ($response->status() === 404) {
                $this->components->warn('Check that the asset UID belongs to the same Kobo account as KOBOTOOLBOX_TOKEN, or share the project with that account.');
            }

            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }

    /**
     * @return array{content: array{survey: array<int, array<string, mixed>>, choices: array<int, array<string, mixed>>}, version_id: string|null}|null
     */
    private function readXlsForm(string $path): ?array
    {
        if (! is_file($path)) {
            $this->components->error("XLSForm file was not found: {$path}");

            return null;
        }

        $workbook = IOFactory::load($path);
        $surveySheet = $workbook->getSheetByName('survey');
        $choicesSheet = $workbook->getSheetByName('choices');

        if (! $surveySheet instanceof Worksheet || ! $choicesSheet instanceof Worksheet) {
            $this->components->error("XLSForm must include 'survey' and 'choices' sheets.");

            return null;
        }

        return [
            'content' => [
                'survey' => $this->sheetRows($surveySheet),
                'choices' => $this->sheetRows($choicesSheet),
            ],
            'version_id' => basename($path),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sheetRows(Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, true);
        $headers = [];
        $payload = [];

        foreach ($rows as $index => $row) {
            if ($index === 1) {
                foreach ($row as $column => $value) {
                    $header = trim((string) $value);

                    if ($header !== '') {
                        $headers[$column] = $header;
                    }
                }

                continue;
            }

            $item = [];

            foreach ($headers as $column => $header) {
                $value = $row[$column] ?? null;

                if ($value === null || trim((string) $value) === '') {
                    continue;
                }

                Arr::set($item, $header, is_string($value) ? trim($value) : $value);
            }

            if ($item !== []) {
                $payload[] = $item;
            }
        }

        return $payload;
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
     * @param  array<string, array<string, string>>  $choiceLabelsByList
     * @return array{0: int, 1: int, 2: int}
     */
    private function importSurvey(string $service, string $tableName, array $survey, array $choiceLabelsByList, HeksKoboChoiceSyncService $choiceSyncService): array
    {
        $groupStack = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $formOrder = 0;

        foreach ($survey as $row) {
            $formOrder++;

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
            $choiceLabels = $this->choiceLabelsForType($type, $choiceLabelsByList);
            [$fieldType, $listName] = $choiceSyncService->selectTypeAndList($type);

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
                        'field_type' => $fieldType,
                        'list_name' => $listName !== '' ? $listName : null,
                        'mapping_status' => 'kobo_form',
                        'confidence' => 'high',
                        'notes' => $this->mappingNotes($mapping, $field, $name, $choiceLabels, $formOrder),
                    ])->save();
                }

                $wasRecentlyCreated ? $created++ : $updated++;
            }
        }

        return [$created, $updated, $skipped];
    }

    /**
     * @param  array<int, mixed>  $choices
     * @return array<string, array<string, string>>
     */
    private function choiceLabelsByList(array $choices): array
    {
        $choiceLabelsByList = [];

        foreach ($choices as $choice) {
            if (! is_array($choice)) {
                continue;
            }

            $listName = trim((string) Arr::get($choice, 'list_name', ''));
            $name = trim((string) Arr::get($choice, 'name', ''));
            $label = $this->displayLabel($choice);

            if ($listName === '' || $name === '' || $label === '') {
                continue;
            }

            $choiceLabelsByList[$listName][$name] = $label;
        }

        return $choiceLabelsByList;
    }

    /**
     * @param  array<string, array<string, string>>  $choiceLabelsByList
     * @return array<string, string>
     */
    private function choiceLabelsForType(string $type, array $choiceLabelsByList): array
    {
        $parts = preg_split('/\s+/', $type) ?: [];
        $listName = $parts[1] ?? '';

        if (! in_array($parts[0] ?? '', ['select_one', 'select_multiple'], true) || $listName === '') {
            return [];
        }

        return $choiceLabelsByList[$listName] ?? [];
    }

    /**
     * @param  array<string, string>  $choiceLabels
     */
    private function mappingNotes(HeksKoboFieldMapping $mapping, string $field, string $name, array $choiceLabels, int $formOrder): ?string
    {
        $notes = $this->existingNotes($mapping);
        $notes['form_order'] = $formOrder;

        if ($field !== $name) {
            $notes['source'] = 'Imported from nested Kobo form path.';
        }

        if ($choiceLabels !== []) {
            $notes['choice_labels'] = $choiceLabels;
        }

        return $notes === [] ? null : json_encode($notes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function existingNotes(HeksKoboFieldMapping $mapping): array
    {
        $notes = trim((string) $mapping->notes);

        if ($notes === '') {
            return [];
        }

        $decoded = json_decode($notes, true);

        return is_array($decoded) ? $decoded : ['source' => $notes];
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
        if ($groupStack !== [] && ! str_contains($name, '/')) {
            return [implode('/', [...$groupStack, $name])];
        }

        return [$name];
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
