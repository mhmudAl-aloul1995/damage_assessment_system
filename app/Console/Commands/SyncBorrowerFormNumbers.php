<?php

namespace App\Console\Commands;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SyncBorrowerFormNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'borrowers:sync-form-numbers
        {file : Absolute path to the Excel workbook containing form numbers and identity numbers}
        {--sheet= : Optional worksheet name. If omitted, all worksheets are scanned}
        {--delete-missing-from-sheet : Delete borrower records whose identity number is not present in the selected sheet}
        {--dry-run : Preview matches without updating the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync borrower form numbers from Excel by matching borrower identity numbers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error('Excel file was not found.');

            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($file);
        $requestedSheet = (string) ($this->option('sheet') ?? '');
        $sheets = $requestedSheet !== ''
            ? [$spreadsheet->getSheetByName($requestedSheet)]
            : iterator_to_array($spreadsheet->getWorksheetIterator());

        if (in_array(null, $sheets, true)) {
            $this->error('The requested worksheet was not found.');

            return self::FAILURE;
        }

        $summary = [
            'rows' => 0,
            'matched' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'missing' => 0,
            'skipped' => 0,
            'deleted' => 0,
        ];
        $sheetIdentityNumbers = [];

        foreach ($sheets as $sheet) {
            if (! $sheet instanceof Worksheet) {
                continue;
            }

            $this->syncSheet($sheet, $summary, $sheetIdentityNumbers);
        }

        if ((bool) $this->option('delete-missing-from-sheet')) {
            $summary['deleted'] = $this->deleteBorrowersMissingFromSheet($sheetIdentityNumbers);
        }

        $this->table(['Indicator', 'Count'], [
            ['Scanned rows', $summary['rows']],
            ['Matched borrowers', $summary['matched']],
            ['Updated form numbers', $summary['updated']],
            ['Already correct', $summary['unchanged']],
            ['Missing borrowers', $summary['missing']],
            ['Skipped rows', $summary['skipped']],
            ['Deleted borrowers', $summary['deleted']],
        ]);

        $this->info((bool) $this->option('dry-run')
            ? 'Dry run completed. No borrower records were updated.'
            : 'Borrower form numbers synced successfully.');

        return self::SUCCESS;
    }

    /**
     * @param  array{rows: int, matched: int, updated: int, unchanged: int, missing: int, skipped: int, deleted: int}  $summary
     * @param  array<string, bool>  $sheetIdentityNumbers
     */
    private function syncSheet(Worksheet $sheet, array &$summary, array &$sheetIdentityNumbers): void
    {
        $rows = $sheet->toArray(null, true, true, true);
        $headerRow = $rows[1] ?? [];
        $formColumn = $this->findColumn($headerRow, ['رقم الاستمارة']);
        $identityColumn = $this->findColumn($headerRow, ['رقم هوية المقترض', 'هوية المقترض']);

        if ($formColumn === null || $identityColumn === null) {
            $summary['skipped'] += max(count($rows) - 1, 0);

            return;
        }

        foreach (array_slice($rows, 1, null, true) as $row) {
            $summary['rows']++;

            $formNumber = $this->text($row[$formColumn] ?? null);
            $identityNumber = $this->identity($row[$identityColumn] ?? null);

            if ($formNumber === '' || $identityNumber === '') {
                $summary['skipped']++;

                continue;
            }

            $sheetIdentityNumbers[$identityNumber] = true;

            $borrower = DamageAssessmentBorrower::query()
                ->where('borrower_id_number', $identityNumber)
                ->first();

            if (! $borrower instanceof DamageAssessmentBorrower) {
                $summary['missing']++;

                continue;
            }

            $summary['matched']++;

            if ((string) $borrower->form_number === $formNumber) {
                $summary['unchanged']++;

                continue;
            }

            $summary['updated']++;

            if (! (bool) $this->option('dry-run')) {
                $borrower->forceFill(['form_number' => $formNumber])->save();
            }
        }
    }

    /**
     * @param  array<string, bool>  $sheetIdentityNumbers
     */
    private function deleteBorrowersMissingFromSheet(array $sheetIdentityNumbers): int
    {
        $identityNumbers = array_keys($sheetIdentityNumbers);

        if ($identityNumbers === []) {
            return 0;
        }

        $query = DamageAssessmentBorrower::query()
            ->whereNotNull('borrower_id_number')
            ->where('borrower_id_number', '<>', '')
            ->whereNotIn('borrower_id_number', $identityNumbers);

        $count = (clone $query)->count();

        if (! (bool) $this->option('dry-run')) {
            $query->delete();
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $headerRow
     * @param  array<int, string>  $needles
     */
    private function findColumn(array $headerRow, array $needles): ?string
    {
        foreach ($headerRow as $column => $heading) {
            $heading = $this->normalizedHeading($heading);

            foreach ($needles as $needle) {
                if (str_contains($heading, $this->normalizedHeading($needle))) {
                    return (string) $column;
                }
            }
        }

        return null;
    }

    private function normalizedHeading(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function identity(mixed $value): string
    {
        return preg_replace('/\D+/', '', $this->text($value)) ?? '';
    }
}
