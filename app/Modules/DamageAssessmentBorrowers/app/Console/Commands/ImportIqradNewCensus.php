<?php

namespace App\Console\Commands;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ImportIqradNewCensus extends Command
{
    private const SHEET_NAME = 'الحصر الجديد';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'borrowers:import-iqrad-new-census
        {path : Excel path containing the الحصر الجديد worksheet}
        {--dry-run : Read and report matches without updating borrowers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import branch address, target housing unit, and unit governorate values from the IQRAD new census worksheet.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->argument('path');
        $path = is_string($path) ? $path : '';
        $fullPath = $this->absolutePath($path);

        if (! is_file($fullPath)) {
            $this->error("Excel file was not found: {$fullPath}");

            return CommandAlias::FAILURE;
        }

        $sheet = IOFactory::load($fullPath)->getSheetByName(self::SHEET_NAME);

        if (! $sheet instanceof Worksheet) {
            $this->error('Sheet [الحصر الجديد] was not found.');

            return CommandAlias::FAILURE;
        }

        $rows = $sheet->toArray(null, true, true, false);
        $headers = $this->headers(array_shift($rows) ?? []);
        $formNumberIndex = $this->columnIndex($headers, ['رقم الاستمارة', 'الاستمارة']);
        $branchAddressIndex = $this->columnIndex($headers, ['العنوان (الفرع)']);
        $targetHousingUnitIndex = $this->columnIndex($headers, ['عنوان الوحدة السكنية المستهدفة بالقرض( المحافظة- المدينة- أقرب معلم)', 'عنوان الوحدة السكنية المستهدفة بالقرض', 'الوحدة السكنية المستهدفة في القرض']);
        $unitGovernorateIndex = $this->columnIndex($headers, ['المحافظة التي بها الوحدة السكنية']);

        if ($formNumberIndex === null || $branchAddressIndex === null || $targetHousingUnitIndex === null || $unitGovernorateIndex === null) {
            $this->error('Required columns were not found: رقم الاستمارة, العنوان (الفرع), عنوان الوحدة السكنية المستهدفة بالقرض, المحافظة التي بها الوحدة السكنية.');

            return CommandAlias::FAILURE;
        }

        $summary = [
            'sheet' => self::SHEET_NAME,
            'total' => 0,
            'matched' => 0,
            'changed' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'issues' => [],
        ];
        $seenFormNumbers = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $formNumber = $this->text($row[$formNumberIndex] ?? null);
            $branchAddress = $this->nullableText($row[$branchAddressIndex] ?? null);
            $targetHousingUnit = $this->nullableText($row[$targetHousingUnitIndex] ?? null);
            $unitGovernorate = $this->nullableText($row[$unitGovernorateIndex] ?? null);

            if ($formNumber === '' && $branchAddress === null && $targetHousingUnit === null && $unitGovernorate === null) {
                continue;
            }

            $summary['total']++;

            if ($formNumber === '') {
                $summary['skipped']++;
                $summary['issues'][] = ['row' => $rowNumber, 'reason' => 'رقم الاستمارة فارغ.'];

                continue;
            }

            if (isset($seenFormNumbers[$formNumber])) {
                $summary['duplicates']++;
                $summary['skipped']++;
                $summary['issues'][] = ['row' => $rowNumber, 'reason' => "رقم الاستمارة مكرر داخل الملف: {$formNumber}."];

                continue;
            }

            $seenFormNumbers[$formNumber] = true;

            $borrower = DamageAssessmentBorrower::query()
                ->whereIn('form_number', $this->formNumberCandidates($formNumber))
                ->first();

            if (! $borrower instanceof DamageAssessmentBorrower) {
                $summary['skipped']++;
                $summary['issues'][] = ['row' => $rowNumber, 'reason' => "لم يتم العثور على استمارة مطابقة: {$formNumber}."];

                continue;
            }

            $summary['matched']++;
            $changes = [
                'loan_branch_address' => $branchAddress,
                'loan_target_housing_unit' => $targetHousingUnit,
                'loan_unit_governorate' => $unitGovernorate,
            ];

            if (! $this->hasChanges($borrower, $changes)) {
                $summary['unchanged']++;

                continue;
            }

            $summary['changed']++;

            if (! (bool) $this->option('dry-run')) {
                $borrower->forceFill($changes)->save();
                $summary['updated']++;
            }
        }

        $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return CommandAlias::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, int>
     */
    private function headers(array $row): array
    {
        $headers = [];

        foreach ($row as $index => $value) {
            $header = $this->text($value);

            if ($header !== '') {
                $headers[$header] = $index;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, int>  $headers
     * @param  array<int, string>  $candidates
     */
    private function columnIndex(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $headers)) {
                return $headers[$candidate];
            }
        }

        foreach ($headers as $header => $index) {
            foreach ($candidates as $candidate) {
                if (str_contains($header, $candidate) || str_contains($candidate, $header)) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (string) (int) $value;
        }

        return trim(str_replace("\u{00A0}", ' ', (string) $value));
    }

    private function nullableText(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text !== '' && $text !== '-' ? $text : null;
    }

    /**
     * @return array<int, string>
     */
    private function formNumberCandidates(string $formNumber): array
    {
        $normalized = preg_replace('/\s+/u', '', $formNumber) ?? $formNumber;
        $candidates = [$formNumber, $normalized];

        if (preg_match('/^([A-Za-z]+)(\d+)$/', $normalized, $matches) === 1) {
            $candidates[] = $matches[1].' '.$matches[2];
        }

        return collect($candidates)
            ->filter(fn (string $candidate): bool => $candidate !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string|null>  $changes
     */
    private function hasChanges(DamageAssessmentBorrower $borrower, array $changes): bool
    {
        foreach ($changes as $key => $value) {
            if (($borrower->{$key} ?? null) !== $value) {
                return true;
            }
        }

        return false;
    }
}
