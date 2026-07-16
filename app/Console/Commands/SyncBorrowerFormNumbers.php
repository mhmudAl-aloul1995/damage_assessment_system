<?php

namespace App\Console\Commands;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerDuplicateMergeService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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
        {file : Absolute path to the Excel workbook containing form numbers}
        {--sheet= : Optional worksheet name. If omitted, all worksheets are scanned}
        {--delete-missing-from-sheet : Delete borrower records whose form number is not present in the selected sheet}
        {--delete-missing-by=form_number : Compare missing borrowers by form_number or identity_number}
        {--delete-missing-by-identity : Compare missing borrowers by identity number from column C}
        {--dedupe-form-numbers : Keep one borrower per form number after applying the selected sheet}
        {--dry-run : Preview matches without updating the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync borrower form numbers from Excel and prune borrowers missing from a sheet';

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
            'deduped' => 0,
            'yellow_line_updated' => 0,
        ];
        $sheetIdentityNumbers = [];
        $sheetFormNumbers = [];

        foreach ($sheets as $sheet) {
            if (! $sheet instanceof Worksheet) {
                continue;
            }

            $this->syncSheet($sheet, $summary, $sheetIdentityNumbers, $sheetFormNumbers);
        }

        if ((bool) $this->option('delete-missing-from-sheet')) {
            $summary['deleted'] = $this->deleteBorrowersMissingFromSheet($sheetIdentityNumbers, $sheetFormNumbers);
        }

        if ((bool) $this->option('dedupe-form-numbers')) {
            $summary['deduped'] = $this->dedupeBorrowersByFormNumber($sheetFormNumbers);
        }

        $this->table(['Indicator', 'Count'], [
            ['Scanned rows', $summary['rows']],
            ['Matched borrowers', $summary['matched']],
            ['Updated form numbers', $summary['updated']],
            ['Already correct', $summary['unchanged']],
            ['Missing borrowers', $summary['missing']],
            ['Skipped rows', $summary['skipped']],
            ['Deleted borrowers', $summary['deleted']],
            ['Deduped borrowers', $summary['deduped']],
            ['Updated yellow line flags', $summary['yellow_line_updated']],
        ]);

        $this->info((bool) $this->option('dry-run')
            ? 'Dry run completed. No borrower records were updated.'
            : 'Borrower form numbers synced successfully.');

        return self::SUCCESS;
    }

    /**
     * @param  array{rows: int, matched: int, updated: int, unchanged: int, missing: int, skipped: int, deleted: int, deduped: int, yellow_line_updated: int}  $summary
     * @param  array<string, bool>  $sheetIdentityNumbers
     * @param  array<string, bool>  $sheetFormNumbers
     */
    private function syncSheet(Worksheet $sheet, array &$summary, array &$sheetIdentityNumbers, array &$sheetFormNumbers): void
    {
        $rows = $sheet->toArray(null, true, true, true);
        $headerRow = $rows[1] ?? [];
        $formColumn = $this->findColumn($headerRow, ['رقم الاستمارة']);
        $identityColumn = $this->findColumn($headerRow, ['رقم هوية المقترض', 'هوية المقترض']);

        $formColumn ??= 'B';
        $identityColumn ??= 'C';

        if ($formColumn === null) {
            $summary['skipped'] += max(count($rows) - 1, 0);

            return;
        }

        foreach (array_slice($rows, 1, null, true) as $row) {
            $summary['rows']++;

            $formNumber = $this->text($row[$formColumn] ?? null);
            $identityNumber = $this->identity($row[$identityColumn] ?? null);
            $insideYellowLine = $this->insideYellowLineValue($row['H'] ?? null);

            if ($formNumber !== '') {
                $sheetFormNumbers[$this->formNumberKey($formNumber)] = true;
            }

            if ($identityNumber !== '') {
                $sheetIdentityNumbers[$identityNumber] = true;
            }

            if ($formNumber === '' || $identityNumber === '') {
                $summary['skipped']++;

                continue;
            }

            $borrowers = $this->matchingBorrowers($identityNumber, $formNumber);
            $borrower = $borrowers->first();

            if (! $borrower instanceof DamageAssessmentBorrower) {
                $summary['missing']++;

                continue;
            }

            $summary['matched']++;

            if ($insideYellowLine !== null) {
                $yellowLineUpdates = $borrowers
                    ->filter(fn (DamageAssessmentBorrower $borrower): bool => $borrower->is_inside_yellow_line !== $insideYellowLine)
                    ->values();

                $summary['yellow_line_updated'] += $yellowLineUpdates->count();

                if ($yellowLineUpdates->isNotEmpty() && ! (bool) $this->option('dry-run')) {
                    DamageAssessmentBorrower::query()
                        ->whereIn('id', $yellowLineUpdates->pluck('id')->all())
                        ->update(['is_inside_yellow_line' => $insideYellowLine]);
                }
            }

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
     * @param  array<string, bool>  $sheetFormNumbers
     */
    private function deleteBorrowersMissingFromSheet(array $sheetIdentityNumbers, array $sheetFormNumbers): int
    {
        $deleteMissingBy = (bool) $this->option('delete-missing-by-identity')
            ? 'identity_number'
            : (string) $this->option('delete-missing-by');

        if (! in_array($deleteMissingBy, ['form_number', 'identity_number'], true)) {
            $this->error('The --delete-missing-by option must be form_number or identity_number.');

            return 0;
        }

        if ($deleteMissingBy === 'identity_number' && $sheetIdentityNumbers === []) {
            return 0;
        }

        if ($deleteMissingBy === 'form_number' && $sheetFormNumbers === []) {
            return 0;
        }

        $borrowers = DamageAssessmentBorrower::query()
            ->orderBy('borrower_name')
            ->get(['id', 'borrower_name', 'borrower_id_number', 'form_number'])
            ->filter(fn (DamageAssessmentBorrower $borrower): bool => $deleteMissingBy === 'identity_number'
                ? ! isset($sheetIdentityNumbers[$this->identity($borrower->borrower_id_number)])
                : ! isset($sheetFormNumbers[$this->formNumberKey($borrower->form_number)])
            )
            ->values();

        $count = $borrowers->count();

        if ($count > 0 && (bool) $this->option('dry-run')) {
            $this->warn($deleteMissingBy === 'identity_number'
                ? 'Borrowers that would be deleted because their identity number is missing from column C:'
                : 'Borrowers that would be deleted because their form number is missing from the sheet:'
            );
            $this->table(
                ['ID', 'Borrower name', 'Identity number', 'Form number'],
                $borrowers->map(fn (DamageAssessmentBorrower $borrower): array => [
                    $borrower->id,
                    $borrower->borrower_name,
                    $borrower->borrower_id_number,
                    $borrower->form_number,
                ])->all()
            );
        }

        if (! (bool) $this->option('dry-run')) {
            DamageAssessmentBorrower::query()
                ->whereIn('id', $borrowers->pluck('id')->all())
                ->delete();
        }

        return $count;
    }

    /**
     * @param  array<string, bool>  $sheetFormNumbers
     */
    private function dedupeBorrowersByFormNumber(array $sheetFormNumbers): int
    {
        if ($sheetFormNumbers === []) {
            return 0;
        }

        $groups = DamageAssessmentBorrower::query()
            ->whereNotNull('form_number')
            ->where('form_number', '<>', '')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (DamageAssessmentBorrower $borrower): bool => isset($sheetFormNumbers[$this->formNumberKey($borrower->form_number)]))
            ->groupBy(fn (DamageAssessmentBorrower $borrower): string => $this->formNumberKey($borrower->form_number))
            ->filter(fn (Collection $borrowers): bool => $borrowers->count() > 1);

        $duplicates = $groups
            ->flatMap(fn (Collection $borrowers): Collection => $borrowers->slice(1))
            ->values();

        if ($duplicates->isNotEmpty() && (bool) $this->option('dry-run')) {
            $this->warn('Duplicate borrowers that would be merged by form number:');
            $this->table(
                ['ID', 'Borrower name', 'Identity number', 'Form number'],
                $duplicates->map(fn (DamageAssessmentBorrower $borrower): array => [
                    $borrower->id,
                    $borrower->borrower_name,
                    $borrower->borrower_id_number,
                    $borrower->form_number,
                ])->all()
            );
        }

        if (! (bool) $this->option('dry-run')) {
            $mergeService = app(BorrowerDuplicateMergeService::class);

            $groups->each(fn (Collection $borrowers): int => $mergeService->mergeBorrowerGroup($borrowers->values()));
        }

        return $duplicates->count();
    }

    /**
     * @return Collection<int, DamageAssessmentBorrower>
     */
    private function matchingBorrowers(string $identityNumber, string $formNumber): Collection
    {
        return DamageAssessmentBorrower::query()
            ->get()
            ->filter(fn (DamageAssessmentBorrower $borrower): bool => $this->identity($borrower->borrower_id_number) === $identityNumber
                || $this->formNumberKey($borrower->form_number) === $this->formNumberKey($formNumber)
            )
            ->values();
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

    private function formNumberKey(mixed $value): string
    {
        return strtoupper(preg_replace('/\s+/u', '', $this->text($value)) ?? '');
    }

    private function insideYellowLineValue(mixed $value): ?bool
    {
        $text = $this->normalizedHeading($value);

        if (str_starts_with($text, 'لا')) {
            return true;
        }

        if (str_starts_with($text, 'نعم')) {
            return false;
        }

        return null;
    }
}
