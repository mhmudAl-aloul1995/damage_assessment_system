<?php

namespace App\Modules\Heks\Services;

use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksImport;
use App\Modules\Heks\Models\HeksLabel;
use App\Modules\Heks\Models\HeksScore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class HeksSpreadsheetImportService
{
    private const SCORE_SHEETS = [
        'Scoring-Heks Final',
        'KOBO_List',
        '125 BNFs -Data',
        '3دفعات',
        'مجموعات العمل',
    ];

    private const FOLLOW_UP_SHEETS = [
        'تقرير المتابعة -هيكس 125',
    ];

    private const LABEL_SHEETS = [
        'Heks Final V1',
    ];

    /**
     * @return array<string, mixed>
     */
    public function preview(UploadedFile $file): array
    {
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);

        return [
            'filename' => $file->getClientOriginalName(),
            'sheets' => collect($reader->listWorksheetInfo($file->getRealPath()))
                ->map(fn (array $sheet): array => [
                    'name' => $sheet['worksheetName'],
                    'rows' => $sheet['totalRows'],
                    'columns' => $sheet['totalColumns'],
                    'detected_type' => $this->detectTypeFromSheetName((string) $sheet['worksheetName']),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{import: HeksImport, summary: array<string, mixed>}
     */
    public function import(UploadedFile $file, string $type, ?int $userId = null): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $summary = [
            'total_rows' => 0,
            'created_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'sheets' => [],
        ];
        $createdImport = null;

        DB::transaction(function () use ($spreadsheet, $file, $type, $userId, &$summary, &$createdImport): void {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $sheetType = $type === 'auto' ? $this->detectType($sheet) : $this->normalizeRequestedType($type, $sheet);

                if ($sheetType === null) {
                    continue;
                }

                $sheetSummary = $this->importSheet($sheet, $sheetType);
                $summary['total_rows'] += $sheetSummary['total'];
                $summary['created_rows'] += $sheetSummary['created'];
                $summary['updated_rows'] += $sheetSummary['updated'];
                $summary['skipped_rows'] += $sheetSummary['skipped'];
                $summary['sheets'][] = array_merge(['name' => $sheet->getTitle(), 'type' => $sheetType], $sheetSummary);
            }

            $createdImport = HeksImport::query()->create([
                'user_id' => $userId,
                'type' => $type,
                'filename' => $file->getClientOriginalName(),
                'sheet_name' => collect($summary['sheets'])->pluck('name')->implode(', '),
                'total_rows' => $summary['total_rows'],
                'created_rows' => $summary['created_rows'],
                'updated_rows' => $summary['updated_rows'],
                'skipped_rows' => $summary['skipped_rows'],
                'summary' => $summary,
            ]);
        });

        $spreadsheet->disconnectWorksheets();

        return [
            'import' => $createdImport,
            'summary' => $summary,
        ];
    }

    private function detectType(Worksheet $sheet): ?string
    {
        $sheetNameType = $this->detectTypeFromSheetName($sheet->getTitle());

        if ($sheetNameType !== null) {
            return $sheetNameType;
        }

        $headers = $this->headers($sheet);

        if ($this->hasAnyHeader($headers, ['Code']) && $this->hasAnyHeader($headers, ['Working condition'])) {
            return 'followups';
        }

        if ($this->hasAnyHeader($headers, ['الكود', 'رقم الطلب/الكود'])) {
            return 'scores';
        }

        return null;
    }

    private function detectTypeFromSheetName(string $sheetName): ?string
    {
        if (in_array($sheetName, self::FOLLOW_UP_SHEETS, true)) {
            return 'followups';
        }

        if (in_array($sheetName, self::LABEL_SHEETS, true)) {
            return 'labels';
        }

        if (in_array($sheetName, self::SCORE_SHEETS, true)) {
            return 'scores';
        }

        return null;
    }

    private function normalizeRequestedType(string $type, Worksheet $sheet): ?string
    {
        if ($type === 'labels' && in_array($sheet->getTitle(), self::LABEL_SHEETS, true)) {
            return 'labels';
        }

        if ($type === 'scores' && in_array($sheet->getTitle(), self::SCORE_SHEETS, true)) {
            return 'scores';
        }

        if ($type === 'followups' && in_array($sheet->getTitle(), self::FOLLOW_UP_SHEETS, true)) {
            return 'followups';
        }

        return $this->detectType($sheet) === $type ? $type : null;
    }

    /**
     * @return array{total: int, created: int, updated: int, skipped: int}
     */
    private function importSheet(Worksheet $sheet, string $type): array
    {
        $headers = $this->headers($sheet);
        $summary = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($this->rows($sheet, $headers) as $row) {
            $summary['total']++;
            $code = $this->code($row);

            if ($code === '') {
                $summary['skipped']++;

                continue;
            }

            $beneficiary = $this->beneficiary($code, $row);
            $wasRecentlyCreated = $beneficiary->wasRecentlyCreated;

            if ($type === 'followups') {
                $this->followUp($beneficiary, $row);
            }

            if ($type === 'scores') {
                $this->score($beneficiary, $row, $sheet->getTitle());
                $this->labels($beneficiary, $row, $sheet->getTitle());
            }

            if ($type === 'labels') {
                $this->labels($beneficiary, $row, $sheet->getTitle());
            }

            $summary[$wasRecentlyCreated ? 'created' : 'updated']++;
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function headers(Worksheet $sheet): array
    {
        $bestRow = 1;
        $bestHeaders = [];

        for ($row = 1; $row <= min(8, $sheet->getHighestDataRow()); $row++) {
            $headers = [];

            for ($column = 1; $column <= $this->highestColumnIndex($sheet); $column++) {
                $heading = $this->text($sheet->getCell([$column, $row]));

                if ($heading !== '') {
                    $headers[$heading] = $column;
                }
            }

            if (count($headers) > count($bestHeaders)) {
                $bestRow = $row;
                $bestHeaders = $headers;
            }
        }

        $bestHeaders['_header_row'] = $bestRow;

        return $bestHeaders;
    }

    /**
     * @param  array<string, int>  $headers
     * @return iterable<int, array<string, mixed>>
     */
    private function rows(Worksheet $sheet, array $headers): iterable
    {
        $headerRow = $headers['_header_row'] ?? 1;
        unset($headers['_header_row']);

        for ($rowNumber = $headerRow + 1; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $row = ['_row_number' => $rowNumber];
            $hasValue = false;

            foreach ($headers as $heading => $column) {
                $value = $this->text($sheet->getCell([$column, $rowNumber]));
                $row[$heading] = $value;
                $hasValue = $hasValue || $value !== '';
            }

            if ($hasValue) {
                yield $rowNumber => $row;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function beneficiary(string $code, array $row): HeksBeneficiary
    {
        $beneficiary = HeksBeneficiary::query()->firstOrNew(['code' => $code]);
        $data = array_filter([
            'name' => $this->first($row, ['Name', 'المستفيد', 'اسم المستفيد', 'اسم رب الأسرة', 'اسم الشخص المقابل']),
            'identity_number' => $this->first($row, ['الهوية', 'هوية المستفيد', 'رقم هوية رب الأسرة', 'رقم الهوية']),
            'phone' => $this->first($row, ['رقم التواصل', 'رقم التواصل2']),
            'alternate_phone' => $this->first($row, ['رقم تواصل بديل']),
            'field_engineer' => $this->first($row, ['اسم المهندس الميداني', 'Engineer Name', 'المهندس المتابع']),
            'visit_date' => $this->date($this->first($row, ['Visit Date', 'تاريخ الزيارة'])),
            'governorate' => $this->first($row, ['المحافظة']),
            'area' => $this->first($row, ['المنطقة/التجمع']),
            'address' => $this->first($row, ['العنوان بالتفصيل']),
            'household_head_gender' => $this->first($row, ['جنس رب الأسرة']),
            'marital_status' => $this->first($row, ['الحالة الاجتماعية']),
            'displacement_status' => $this->first($row, ['حالة النزوح للأسرة حالياً']),
            'occupancy_status' => $this->first($row, ['حالة الإشغال الحالي للوحدة السكنية']),
            'damage_status' => $this->first($row, ['تقييم حالة ضرر المأوى:']),
            'grant_amount' => $this->decimal($this->first($row, ['GRANT', 'المنحة', 'قيمة العقد ILS'])),
            'payment_1' => $this->decimal($this->first($row, ['Payment_1', '30%', 'الدفعة الأولى  30% ILS'])),
            'payment_2' => $this->decimal($this->first($row, ['Payment_2', '50%'])),
            'payment_3' => $this->decimal($this->first($row, ['Payment_3', '20%'])),
            'social_notes' => $this->first($row, ['ملاحظات إجتماعية']),
            'engineer_notes' => $this->first($row, ['ملاحظات المهندسين']),
            'recommendations' => $this->first($row, ['توصيات المهندس للزيارة', 'توصيات نهائية']),
            'raw_data' => array_merge($beneficiary->raw_data ?? [], $row),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $beneficiary->fill($data)->save();

        return $beneficiary;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function followUp(HeksBeneficiary $beneficiary, array $row): void
    {
        HeksFollowUp::query()->updateOrCreate(
            [
                'heks_beneficiary_id' => $beneficiary->id,
                'code' => $beneficiary->code,
                'visit_number' => $this->first($row, ['visit #']) ?: null,
            ],
            [
                'visit_date' => $this->date($this->first($row, ['Visit Date'])),
                'engineer_name' => $this->first($row, ['Engineer Name']),
                'working_condition' => $this->first($row, ['Working condition']),
                'other_condition' => $this->first($row, ['Other condition:']),
                'completed_amount_ils' => $this->decimal($this->first($row, ['إجمالي ما تم انجازة حتى الآن ILS'])),
                'completion_percentage' => $this->decimal($this->first($row, ['نسبة الإنجاز بالأعمال %'])),
                'engineer_recommendations' => $this->first($row, ['توصيات المهندس للزيارة']),
                'boq_filename' => $this->first($row, ['Insert BOQ']),
                'boq_url' => $this->first($row, ['Insert BOQ_URL']),
                'raw_data' => $row,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function score(HeksBeneficiary $beneficiary, array $row, string $source): void
    {
        HeksScore::query()->updateOrCreate(
            ['heks_beneficiary_id' => $beneficiary->id, 'source' => $source],
            [
                'grant_amount' => $this->decimal($this->first($row, ['GRANT', 'المنحة', 'قيمة العقد ILS'])),
                'payment_1' => $this->decimal($this->first($row, ['Payment_1', '30%', 'الدفعة الأولى  30% ILS'])),
                'payment_2' => $this->decimal($this->first($row, ['Payment_2', '50%'])),
                'payment_3' => $this->decimal($this->first($row, ['Payment_3', '20%'])),
                'social_score' => $this->decimal($this->first($row, ['تقييم الحالة الاجتماعية من 35', 'تقييم الحالة الاجتماعية  (30)'])),
                'technical_score' => $this->decimal($this->first($row, ['تقييم الحالة الفنية (70)'])),
                'total_score' => $this->decimal($this->first($row, ['Total Score', 'Score', 'score'])),
                'raw_data' => $row,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function labels(HeksBeneficiary $beneficiary, array $row, string $source): void
    {
        $keys = [
            'screening' => 'يرجى تحديد ما إذا كانت هناك أي من الحالات التالية تنطبق على الوحدة السكنية.',
            'damage_status' => 'تقييم حالة ضرر المأوى:',
            'roof_status' => 'حالة السقف',
            'kitchen_status' => 'حالة المطبخ:',
            'occupancy_status' => 'حالة الإشغال الحالي للوحدة السكنية',
            'final_recommendation' => 'توصيات نهائية',
            'social_score' => 'تقييم الحالة الاجتماعية  (30)',
            'technical_score' => 'تقييم الحالة الفنية (70)',
        ];

        foreach ($keys as $labelKey => $heading) {
            $value = $this->first($row, [$heading]);

            if ($value === '') {
                continue;
            }

            HeksLabel::query()->updateOrCreate(
                ['heks_beneficiary_id' => $beneficiary->id, 'source' => $source, 'label_key' => $labelKey],
                ['label_value' => $value, 'version' => $this->first($row, ['__version__', '_submission___version__']), 'raw_data' => $row]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function code(array $row): string
    {
        return $this->first($row, ['Code', 'الكود', 'رقم الطلب/الكود']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function first(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (($row[$key] ?? '') !== '') {
                return trim((string) $row[$key]);
            }
        }

        foreach ($row as $heading => $value) {
            foreach ($keys as $key) {
                if (is_string($heading) && str_contains($heading, $key) && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, int>  $headers
     * @param  array<int, string>  $keys
     */
    private function hasAnyHeader(array $headers, array $keys): bool
    {
        foreach ($keys as $key) {
            if (isset($headers[$key])) {
                return true;
            }

            foreach (array_keys($headers) as $heading) {
                if (is_string($heading) && str_contains($heading, $key)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function text(Cell $cell): string
    {
        try {
            $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
        } catch (Throwable) {
            $value = $cell->getValue();
        }

        return trim((string) ($value ?? ''));
    }

    private function decimal(string $value): ?float
    {
        $normalized = str_replace([',', ' ', '%'], '', $value);

        return $normalized !== '' && is_numeric($normalized) ? (float) $normalized : null;
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function highestColumnIndex(Worksheet $sheet): int
    {
        $index = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        if ($index > 350) {
            throw new RuntimeException('ملف HEKS يحتوي عدداً غير متوقع من الأعمدة.');
        }

        return $index;
    }
}
