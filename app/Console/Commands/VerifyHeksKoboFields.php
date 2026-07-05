<?php

namespace App\Console\Commands;

use App\Models\KoboRestSubmission;
use App\Modules\Heks\Models\HeksKoboFieldMapping;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VerifyHeksKoboFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heks:kobo-verify-fields {service? : Optional HEKS Kobo service name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that HEKS Kobo payload fields are stored in service record columns';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $services = filled($this->argument('service'))
            ? [(string) $this->argument('service')]
            : ['heks-main', 'heks-followups', 'heks-boq', 'heks-followup-boq'];

        $checked = 0;
        $missing = [];

        KoboRestSubmission::query()
            ->whereIn('service_name', $services)
            ->where('sync_status', 'synced')
            ->orderBy('id')
            ->chunkById(100, function ($submissions) use (&$checked, &$missing): void {
                foreach ($submissions as $submission) {
                    $tableName = $this->koboRecordTable($submission->service_name);

                    if ($tableName === null || ! Schema::hasTable($tableName)) {
                        $missing[] = "#{$submission->id} {$submission->service_name}: missing record table";

                        continue;
                    }

                    $record = DB::table($tableName)
                        ->where('submission_uuid', $submission->submission_uuid)
                        ->first();

                    if ($record === null) {
                        $missing[] = "#{$submission->id} {$submission->submission_uuid}: missing wide record";

                        continue;
                    }

                    foreach ($this->flatten($submission->payload ?? []) as $field => $value) {
                        if (! is_string($field) || $field === '') {
                            continue;
                        }

                        $checked++;
                        $column = $this->koboColumnName($field);
                        $mappingExists = HeksKoboFieldMapping::query()
                            ->where('service_name', $submission->service_name)
                            ->where('kobo_field', $field)
                            ->where('column_name', $column)
                            ->exists();

                        if (! $mappingExists) {
                            $missing[] = "#{$submission->id} {$field}: missing mapping";

                            continue;
                        }

                        if (! Schema::hasColumn($tableName, $column)) {
                            $missing[] = "#{$submission->id} {$field}: missing column {$column}";

                            continue;
                        }

                        $expected = $this->recordValue($value);
                        $actual = property_exists($record, $column) ? $record->{$column} : null;

                        if ((string) $actual !== (string) $expected) {
                            $missing[] = "#{$submission->id} {$field}: value mismatch";
                        }
                    }
                }
            });

        if ($missing !== []) {
            foreach (array_slice($missing, 0, 25) as $issue) {
                $this->components->warn($issue);
            }

            $this->components->error('HEKS Kobo field verification failed. Checked: '.$checked.', issues: '.count($missing).'.');

            return self::FAILURE;
        }

        $this->components->info('HEKS Kobo field verification passed. Checked fields: '.$checked.'.');

        return self::SUCCESS;
    }

    private function koboRecordTable(string $service): ?string
    {
        return match ($service) {
            'heks-main' => 'heks_main_kobo_records',
            'heks-followups' => 'heks_followups_kobo_records',
            'heks-boq' => 'heks_boq_kobo_records',
            'heks-followup-boq' => 'heks_followup_boq_kobo_records',
            default => null,
        };
    }

    private function koboColumnName(string $field): string
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

    private function recordValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flatten(array $payload, string $prefix = ''): array
    {
        $flat = [];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}/{$key}";

            if (is_array($value) && ! array_is_list($value)) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = $value;
                $flat[(string) $key] = $value;
            }
        }

        return $flat;
    }
}
