<?php

namespace App\Console\Commands;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Console\Command\Command as CommandAlias;

class UpdateBorrowerLoanNetAmounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'borrowers:update-loan-net-amounts
        {path : Excel path containing loan number and net loan amount columns}
        {--sheet= : Optional worksheet name; defaults to the first sheet}
        {--dry-run : Read and report matches without updating borrowers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update borrower loan net amounts from an Excel workbook matched by loan number.';

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

        $spreadsheet = IOFactory::load($fullPath);
        $sheetName = $this->option('sheet');
        $sheet = is_string($sheetName) && $sheetName !== ''
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getSheet(0);

        if (! $sheet instanceof Worksheet) {
            $this->error("Sheet [{$sheetName}] was not found.");

            return CommandAlias::FAILURE;
        }

        $rows = $sheet->toArray(null, true, true, false);
        $headers = $this->headers(array_shift($rows) ?? []);
        $loanNumberIndex = $headers['رقم القرض'] ?? null;
        $loanNetAmountIndex = $headers['صافي مبلغ القرض'] ?? null;

        if ($loanNumberIndex === null || $loanNetAmountIndex === null) {
            $this->error('Required columns were not found: رقم القرض, صافي مبلغ القرض.');

            return CommandAlias::FAILURE;
        }

        $summary = [
            'total' => 0,
            'matched' => 0,
            'changed' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'issues' => [],
        ];
        $seenLoanNumbers = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $loanNumber = $this->text($row[$loanNumberIndex] ?? null);
            $loanNetAmount = $this->decimal($this->text($row[$loanNetAmountIndex] ?? null));

            if ($loanNumber === '' && $loanNetAmount === null) {
                continue;
            }

            $summary['total']++;

            if ($loanNumber === '') {
                $summary['skipped']++;
                $summary['issues'][] = ['row' => $rowNumber, 'reason' => 'رقم القرض فارغ.'];

                continue;
            }

            if (isset($seenLoanNumbers[$loanNumber])) {
                $summary['duplicates']++;
                $summary['skipped']++;
                $summary['issues'][] = ['row' => $rowNumber, 'reason' => "رقم القرض مكرر داخل الملف: {$loanNumber}."];

                continue;
            }

            $seenLoanNumbers[$loanNumber] = true;

            if ($loanNetAmount === null) {
                $summary['skipped']++;
                $summary['issues'][] = ['row' => $rowNumber, 'reason' => "صافي مبلغ القرض غير صالح للقرض {$loanNumber}."];

                continue;
            }

            $borrower = DamageAssessmentBorrower::query()
                ->where('loan_number', $loanNumber)
                ->first();

            if (! $borrower instanceof DamageAssessmentBorrower) {
                $summary['skipped']++;
                $summary['issues'][] = ['row' => $rowNumber, 'reason' => "لم يتم العثور على قرض مطابق: {$loanNumber}."];

                continue;
            }

            $summary['matched']++;

            if ($this->amountsAreEqual($borrower->loan_net_amount, $loanNetAmount)) {
                $summary['unchanged']++;

                continue;
            }

            $summary['changed']++;

            if (! (bool) $this->option('dry-run')) {
                $borrower->forceFill(['loan_net_amount' => $loanNetAmount])->save();
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

    private function decimal(string $value): ?float
    {
        $normalizedValue = str_replace([',', ' ', "\u{00A0}"], '', trim($value));

        return $normalizedValue === '' || ! is_numeric($normalizedValue) ? null : round((float) $normalizedValue, 2);
    }

    private function amountsAreEqual(mixed $currentAmount, float $newAmount): bool
    {
        if ($currentAmount === null) {
            return false;
        }

        return round((float) $currentAmount, 2) === round($newAmount, 2);
    }
}
