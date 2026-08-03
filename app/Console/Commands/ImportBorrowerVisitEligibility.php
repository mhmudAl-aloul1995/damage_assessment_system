<?php

namespace App\Console\Commands;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ImportBorrowerVisitEligibility extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'borrowers:import-visit-eligibility
        {path=app/IDB2.xlsx : Excel path relative to project base path}
        {--dry-run : Read and report matches without updating borrowers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import borrower visit eligibility, branch address, and guarantor values from the IDB Excel workbook.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->argument('path');
        $path = is_string($path) ? $path : 'app/IDB2.xlsx';
        $fullPath = $this->absolutePath($path);

        if (! is_file($fullPath)) {
            $this->error("Excel file was not found: {$fullPath}");

            return CommandAlias::FAILURE;
        }

        $sheet = IOFactory::load($fullPath)->getSheetByName('371 Beneficiaries');

        if (! $sheet instanceof Worksheet) {
            $this->error('Sheet [371 Beneficiaries] was not found.');

            return CommandAlias::FAILURE;
        }

        $summary = [
            'total' => 0,
            'matched' => 0,
            'updated' => 0,
            'skipped' => 0,
            'unknown_values' => 0,
            'counts' => ['yes' => 0, 'no' => 0, 'yes_star' => 0, 'unknown' => 0],
        ];

        foreach ($sheet->toArray(null, true, true, false) as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $formNumber = $this->text($row[1] ?? null);
            $idNumber = $this->text($row[2] ?? null);
            $branchAddress = $this->text($row[6] ?? null);
            $rawEligibility = $this->text($row[9] ?? null);
            $guarantors = $this->guarantors($row);

            if ($formNumber === '' && $idNumber === '' && $rawEligibility === '') {
                continue;
            }

            $summary['total']++;
            $eligibility = $this->visitEligibility($rawEligibility);

            if ($eligibility === null) {
                $summary['unknown_values']++;
                $summary['skipped']++;

                continue;
            }

            $summary['counts'][$eligibility]++;

            $borrower = DamageAssessmentBorrower::query()
                ->when($formNumber !== '', fn ($query) => $query->where('form_number', $formNumber))
                ->when($formNumber === '' && $idNumber !== '', fn ($query) => $query->where('borrower_id_number', $idNumber))
                ->first();

            if (! $borrower instanceof DamageAssessmentBorrower && $idNumber !== '') {
                $borrower = DamageAssessmentBorrower::query()
                    ->where('borrower_id_number', $idNumber)
                    ->first();
            }

            if (! $borrower instanceof DamageAssessmentBorrower) {
                $summary['skipped']++;

                continue;
            }

            $summary['matched']++;

            if (! $this->option('dry-run')) {
                $borrower->forceFill([
                    'visit_eligibility' => $eligibility,
                    'loan_branch_address' => $branchAddress ?: null,
                    'loan_guarantors' => $guarantors,
                ])->save();
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

    private function visitEligibility(string $value): ?string
    {
        return match ($value) {
            'نعم' => 'yes',
            'لا' => 'no',
            'نعم*' => 'yes_star',
            '', 'غير معروف' => 'unknown',
            default => null,
        };
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, array{name: string, phone: ?string}>
     */
    private function guarantors(array $row): array
    {
        $guarantors = [];

        foreach ([[12, 13], [14, 15], [16, 17]] as [$nameIndex, $phoneIndex]) {
            $name = $this->text($row[$nameIndex] ?? null);
            $phone = $this->text($row[$phoneIndex] ?? null);

            if ($name === '' && $phone === '') {
                continue;
            }

            $guarantors[] = [
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
            ];
        }

        return $guarantors;
    }
}
