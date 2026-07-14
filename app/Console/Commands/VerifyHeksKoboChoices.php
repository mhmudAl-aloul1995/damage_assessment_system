<?php

namespace App\Console\Commands;

use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksKoboChoice;
use App\Modules\Heks\Models\HeksKoboFieldMapping;
use App\Modules\Heks\Services\HeksKoboValueDisplayService;
use Illuminate\Console\Command;

class VerifyHeksKoboChoices extends Command
{
    protected $signature = 'heks:kobo-choices:verify
        {service : HEKS service name, for example heks-main}
        {--beneficiary= : Beneficiary id to scan raw_data values}
        {--question= : Limit verification to one question key}
        {--fix : Reserved for safe future fixes}';

    protected $description = 'Verify HEKS Kobo select fields and unresolved raw choice values';

    public function handle(HeksKoboValueDisplayService $displayService): int
    {
        $service = (string) $this->argument('service');
        $question = $this->option('question') ? (string) $this->option('question') : null;

        $selectOne = HeksKoboFieldMapping::query()
            ->where('service_name', $service)
            ->where('field_type', 'select_one')
            ->when($question, fn ($query) => $query->where('kobo_field', $question))
            ->count();

        $selectMultiple = HeksKoboFieldMapping::query()
            ->where('service_name', $service)
            ->where('field_type', 'select_multiple')
            ->when($question, fn ($query) => $query->where('kobo_field', $question))
            ->count();

        $lists = HeksKoboChoice::query()
            ->where('service_name', $service)
            ->where('is_active', true)
            ->distinct('list_name')
            ->count('list_name');

        $choices = HeksKoboChoice::query()
            ->where('service_name', $service)
            ->where('is_active', true)
            ->count();

        $this->components->info("select_one fields: {$selectOne}");
        $this->components->info("select_multiple fields: {$selectMultiple}");
        $this->components->info("choice lists: {$lists}");
        $this->components->info("active choices: {$choices}");

        if ($this->option('beneficiary')) {
            $this->scanBeneficiary((int) $this->option('beneficiary'), $service, $displayService, $question);
        }

        return self::SUCCESS;
    }

    private function scanBeneficiary(int $beneficiaryId, string $service, HeksKoboValueDisplayService $displayService, ?string $question): void
    {
        $beneficiary = HeksBeneficiary::query()->find($beneficiaryId);

        if (! $beneficiary instanceof HeksBeneficiary || ! is_array($beneficiary->raw_data)) {
            $this->components->warn('Beneficiary raw_data was not found.');

            return;
        }

        $values = $beneficiary->raw_data[$service] ?? $beneficiary->raw_data[str_replace('_', '-', $service)] ?? null;

        if (! is_array($values)) {
            $this->components->warn('No raw_data section was found for this service.');

            return;
        }

        foreach ($this->flatten($values) as $field => $value) {
            if ($question !== null && $field !== $question) {
                continue;
            }

            $resolved = $displayService->resolve($service, (string) $field, $value);

            if ($resolved['warning'] !== null) {
                $this->line($field.' => '.$resolved['warning']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'/'.(string) $key;

            if (is_array($value)) {
                $flat = array_replace($flat, $this->flatten($value, $path));

                continue;
            }

            $flat[$path] = $value;
        }

        return $flat;
    }
}
