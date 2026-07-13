<?php

namespace App\Modules\DamageAssessmentBorrowers\Services;

use App\Modules\DamageAssessmentBorrowers\Models\BorrowerBoqCatalogItem;
use App\Modules\DamageAssessmentBorrowers\Models\BorrowerPricingSetting;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class BorrowerSpreadsheetImportService
{
    /**
     * @var array<string, string>
     */
    private const EMPLOYMENT_MAP = [
        'على رأس عمله' => 'working',
        'متقاعد' => 'retired',
        'لا يعمل حاليا' => 'not_working',
        'لا يعمل حالياً' => 'not_working',
        'لا يعمل' => 'not_working',
    ];

    /**
     * @var array<string, string>
     */
    private const MARITAL_MAP = [
        'متزوج/ة' => 'married',
        'أعزب/عزباء' => 'single',
        'أعزب/ آنسة' => 'single',
        'أرمل/ة' => 'widowed',
        'مطلق/ة' => 'divorced',
        'مهجور/ة' => 'abandoned',
    ];

    /**
     * @var array<string, string>
     */
    private const DISPLACEMENT_MAP = [
        'نازح' => 'displaced',
        'عائد الى منزله' => 'returned',
        'عائد إلى منزله' => 'returned',
        'مقيم ( لم ينزح من منزله)' => 'resident',
        'مقيم' => 'resident',
    ];

    /**
     * @var array<string, string>
     */
    private const GOVERNORATE_MAP = [
        'محافظة الشمال' => 'north',
        'محافظة غزة' => 'gaza',
        'محافظة الوسطى' => 'middle',
        'محافظة خانيونس' => 'khan_younis',
        'محافظة رفح' => 'rafah',
    ];

    /**
     * @var array<string, string>
     */
    private const OCCUPANCY_MAP = [
        'المالك نفسه (المقترض)' => 'owner_borrower',
        'لا يوجد (في حال الوحدة السكنية هدم كلي او بليغ غيرصالح للسكن)' => 'none_due_damage',
        'لا يوجد بسبب الضرر' => 'none_due_damage',
        'مستضافين او نازحين' => 'displaced_hosted',
        'مستأجرين' => 'tenants',
        'مشترين' => 'buyers',
        'وارثين' => 'heirs',
    ];

    /**
     * @var array<string, string>
     */
    private const DAMAGE_MAP = [
        'هدم كلي' => 'destroyed',
        'متضرر بليغ غير صالح للسكن' => 'severe_uninhabitable',
        'متضرر بليغ صالح للسكن' => 'severe_habitable',
        'متضرر أضرار طفيفة' => 'minor',
        'أضرار طفيفة' => 'minor',
    ];

    public function __construct(
        private readonly BorrowerRiskAnalysisService $riskAnalysis,
        private readonly BorrowerDamageValuationService $damageValuation,
    ) {}

    /**
     * @return array{total: int, imported: int, skipped: int, exchange_rate: float}
     */
    public function importPriceCatalog(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('BOQ price source file was not found.');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('ورقة1') ?? $spreadsheet->getSheet(0);
        $exchangeRate = $this->currentExchangeRate();
        $rows = $sheet->toArray(null, true, true, false);
        array_shift($rows);

        $summary = [
            'total' => 0,
            'imported' => 0,
            'skipped' => 0,
            'exchange_rate' => $exchangeRate,
        ];
        $category = null;

        foreach ($rows as $index => $row) {
            $code = $this->text($row[0] ?? null);
            $description = $this->text($row[1] ?? null);
            $unit = $this->text($row[2] ?? null);
            $unitPrice = $this->decimal($this->text($row[3] ?? null)) ?? 0.0;

            if ($code !== '' && ! is_numeric(str_replace('.', '', $code))) {
                $category = $code;

                continue;
            }

            if ($description === '' || $unit === '' || ! is_numeric(str_replace('.', '', $code))) {
                continue;
            }

            $summary['total']++;

            if ($unitPrice <= 0) {
                $summary['skipped']++;

                continue;
            }

            BorrowerBoqCatalogItem::query()->updateOrCreate(
                ['item_code' => $code],
                [
                    'description' => $description,
                    'normalized_description' => $this->normalizeDescription($description),
                    'source_key' => sha1($description),
                    'unit' => $unit,
                    'unit_price' => $unitPrice,
                    'unit_price_ils' => round($unitPrice * $exchangeRate, 2),
                    'category' => $category,
                    'source_sheet' => $sheet->getTitle(),
                    'sort_order' => $index + 1,
                ]
            );
            $summary['imported']++;
        }

        return $summary;
    }

    /**
     * @return array{
     *     total: int,
     *     ready: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     issues: array<int, array{row: int, reasons: array<int, string>}>,
     *     duplicate_form_numbers: int,
     *     risk_levels: array{critical: int, high: int, medium: int, low: int}
     * }
     */
    public function import(string $path, bool $dryRun = false, bool $includeDuplicateIdentities = false): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Import source file was not found.');
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Import source file is not valid JSON.', previous: $exception);
        }

        $sourceRows = collect($payload['records'] ?? [])
            ->filter(fn (mixed $sourceRow): bool => is_array($sourceRow))
            ->map(fn (array $sourceRow): array => $this->sourceRow($sourceRow))
            ->values()
            ->all();

        return $this->importRows($sourceRows, $dryRun, $includeDuplicateIdentities);
    }

    /**
     * @return array{
     *     total: int,
     *     ready: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     issues: array<int, array{row: int, reasons: array<int, string>}>,
     *     duplicate_form_numbers: int,
     *     risk_levels: array{critical: int, high: int, medium: int, low: int}
     * }
     */
    public function importWorkbook(string $path, bool $dryRun = false, bool $includeDuplicateIdentities = false): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('تعذر قراءة ملف Excel على الخادم لأن امتداد PHP Zip غير مفعّل.');
        }

        $spreadsheet = IOFactory::load($path);
        $deceasedGuarantors = $this->repeatedNamesByUuid($spreadsheet->getSheetByName('group_oy4mv92') ?? $spreadsheet->getSheet(1));
        $affectedGuarantors = $this->repeatedNamesByUuid($spreadsheet->getSheetByName('group_ax0jk91') ?? $spreadsheet->getSheet(2));
        $attachments = $this->attachmentsByUuid($spreadsheet->getSheetByName('group_zp2bk31'));
        $residentHouseholds = $this->residentHouseholdsByUuid($spreadsheet->getSheetByName('group_fl9zq19'));
        $rows = $spreadsheet->getSheet(0)->toArray(null, true, true, false);
        $headers = $this->headers(array_shift($rows) ?? []);
        $sourceRows = collect($this->sourceRowsFromWorksheet($rows, $headers, $affectedGuarantors, $deceasedGuarantors, $attachments, $residentHouseholds))
            ->map(fn (array $sourceRow): array => $this->sourceRow($sourceRow))
            ->all();

        return $this->importRows($sourceRows, $dryRun, $includeDuplicateIdentities);
    }

    /**
     * @return array{source: string, sheets: array<int, array{name: string, status: string, total: int, ready: int, skipped: int, sample: array<int, array<string, mixed>>}>}
     */
    public function previewLoanWorkbook(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheets = collect($spreadsheet->getWorksheetIterator())
            ->filter(fn (Worksheet $sheet): bool => $this->loanSheetStatus($sheet->getTitle()) !== null)
            ->map(function (Worksheet $sheet): array {
                $rows = $this->loanRows($sheet);
                $readyRows = collect($rows)->filter(fn (array $row): bool => $row['borrower_name'] !== '' && $row['borrower_id_number'] !== '');

                return [
                    'name' => $sheet->getTitle(),
                    'status' => $this->loanSheetStatus($sheet->getTitle()),
                    'total' => count($rows),
                    'ready' => $readyRows->count(),
                    'skipped' => count($rows) - $readyRows->count(),
                    'sample' => $readyRows->take(5)->map(fn (array $row): array => [
                        'loan_number' => $row['loan_number'],
                        'borrower_name' => $row['borrower_name'],
                        'borrower_id_number' => $row['borrower_id_number'],
                        'loan_balance' => $row['loan_balance'],
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        if ($sheets === []) {
            throw new RuntimeException('لم يتم العثور على ورقتَي القروض «نشطه» أو «مغلقه» داخل الملف.');
        }

        return ['source' => 'kuwait-loans', 'sheets' => $sheets];
    }

    /**
     * @return array{source: string, sheets: array<int, array{name: string, status: string, total: int, ready: int, skipped: int, sample: array<int, array<string, mixed>>}>}
     */
    public function previewWorkbook(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('تعذر قراءة ملف Excel على الخادم لأن امتداد PHP Zip غير مفعّل.');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);
        $headers = $this->headers(array_shift($rows) ?? []);
        $sourceRows = collect($this->sourceRowsFromWorksheet($rows, $headers, [], [], [], []))
            ->map(fn (array $sourceRow): array => $this->sourceRow($sourceRow))
            ->values();

        if ($sourceRows->isEmpty()) {
            throw new RuntimeException('لم يتم العثور على صفوف استبيان قابلة للمعاينة داخل الملف.');
        }

        $readyRows = $sourceRows->filter(fn (array $row): bool => $row['borrower_name'] !== '' && $row['borrower_id_number'] !== '');

        return [
            'source' => 'borrower-survey',
            'sheets' => [[
                'name' => $sheet->getTitle(),
                'status' => 'survey',
                'total' => $sourceRows->count(),
                'ready' => $readyRows->count(),
                'skipped' => $sourceRows->count() - $readyRows->count(),
                'sample' => $readyRows->take(5)->map(fn (array $row): array => [
                    'form_number' => $row['form_number'],
                    'borrower_name' => $row['borrower_name'],
                    'borrower_id_number' => $row['borrower_id_number'],
                    'loan_unit_floor_type' => $row['loan_unit_floor_type_label'],
                ])->values()->all(),
            ]],
        ];
    }

    /**
     * @return array{total: int, ready: int, created: int, updated: int, skipped: int, issues: array<int, array{row: int, reasons: array<int, string>}>, duplicate_form_numbers: int, risk_levels: array{critical: int, high: int, medium: int, low: int}}
     */
    public function importLoanWorkbook(string $path, string $sheetName): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (! $sheet instanceof Worksheet || $this->loanSheetStatus($sheetName) === null) {
            throw new RuntimeException('ورقة القروض المحددة غير متاحة للاستيراد.');
        }

        $summary = [
            'total' => 0,
            'ready' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'issues' => [],
            'duplicate_form_numbers' => 0,
            'risk_levels' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0],
        ];

        foreach ($this->loanRows($sheet) as $row) {
            $summary['total']++;

            if ($row['borrower_name'] === '' || $row['borrower_id_number'] === '') {
                $summary['skipped']++;
                $summary['issues'][] = ['row' => $row['row_number'], 'reasons' => ['اسم المقترض أو رقم الهوية مفقود.']];

                continue;
            }

            $summary['ready']++;
            $borrower = DamageAssessmentBorrower::query()
                ->where('borrower_id_number', $row['borrower_id_number'])
                ->orWhere('loan_number', $row['loan_number'])
                ->firstOrNew();
            $isNew = ! $borrower->exists;

            $borrower->fill(array_filter([
                'source_uuid' => $isNew ? 'kuwait-loan-'.$row['loan_number'] : null,
                'form_number' => $row['loan_number'],
                'loan_number' => $row['loan_number'],
                'loan_status' => $row['loan_status'],
                'loan_original_amount' => $row['loan_original_amount'],
                'loan_total_amount' => $row['loan_total_amount'],
                'loan_portfolio_amount' => $row['loan_portfolio_amount'],
                'loan_net_amount' => $row['loan_net_amount'],
                'loan_balance' => $row['loan_balance'],
                'loan_paid_amount' => $row['loan_paid_amount'],
                'loan_installments_count' => $row['loan_installments_count'],
                'loan_started_at' => $row['loan_started_at'],
                'loan_last_installment_at' => $row['loan_last_installment_at'],
                'loan_clearance_delivered' => $row['loan_clearance_delivered'],
                'borrower_name' => $row['borrower_name'],
                'borrower_id_number' => $row['borrower_id_number'],
                'phone_primary' => $row['phone_primary'],
                'current_residence_address' => $row['address'],
                'loan_unit_address' => $row['address'],
            ], fn (mixed $value): bool => $value !== null && $value !== ''));
            $borrower->save();
            $summary[$isNew ? 'created' : 'updated']++;
        }

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceRows
     * @return array{
     *     total: int,
     *     ready: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     issues: array<int, array{row: int, reasons: array<int, string>}>,
     *     duplicate_form_numbers: int,
     *     risk_levels: array{critical: int, high: int, medium: int, low: int}
     * }
     */
    private function importRows(array $sourceRows, bool $dryRun, bool $includeDuplicateIdentities): array
    {
        $duplicateIdentityValues = collect($sourceRows)
            ->pluck('borrower_id_number')
            ->filter()
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->keys()
            ->all();
        $duplicateFormNumbers = collect($sourceRows)
            ->pluck('form_number')
            ->filter()
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->sum(fn (int $count): int => $count - 1);

        $summary = [
            'total' => count($sourceRows),
            'ready' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'issues' => [],
            'duplicate_form_numbers' => $duplicateFormNumbers,
            'risk_levels' => [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
        ];
        $readyRows = [];

        foreach ($sourceRows as $sourceRow) {
            $reasons = $this->validateMappedRow($sourceRow);

            if (! $includeDuplicateIdentities && in_array($sourceRow['borrower_id_number'], $duplicateIdentityValues, false)) {
                $reasons[] = 'رقم هوية المقترض مكرر داخل ملف المصدر.';
            }

            if ($reasons !== []) {
                $summary['issues'][] = [
                    'row' => $sourceRow['row_number'],
                    'reasons' => array_values(array_unique($reasons)),
                ];
                $summary['skipped']++;

                continue;
            }

            $data = $this->mappedData($sourceRow);
            $analysis = $this->riskAnalysis->analyze($data);
            $summary['risk_levels'][$analysis['risk_level']]++;
            $summary['ready']++;
            $readyRows[] = array_merge($data, $analysis);
        }

        if (! $dryRun) {
            DB::transaction(function () use ($readyRows, &$summary): void {
                foreach ($readyRows as $data) {
                    $boqItems = $data['boq_items'] ?? [];
                    $attachments = $data['attachments'] ?? [];
                    $residentHouseholds = $data['resident_households'] ?? [];
                    unset($data['boq_items'], $data['attachments']);

                    $borrower = $this->saveImportedBorrower($data);
                    $this->syncBoqItems($borrower, $boqItems);
                    $this->syncAttachments($borrower, $attachments);
                    $this->syncResidentHouseholds($borrower, $residentHouseholds);

                    if ($borrower->wasRecentlyCreated) {
                        $summary['created']++;
                    } else {
                        $summary['updated']++;
                    }
                }
            });
        }

        return $summary;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, array<int, int>>
     */
    private function headers(array $headerRow): array
    {
        $headers = [];

        foreach ($headerRow as $index => $heading) {
            $heading = $this->text($heading);

            if ($heading !== '') {
                $headers[$heading][] = $index;
            }
        }

        return $headers;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, array<int, int>>  $headers
     * @param  array<string, array<int, string>>  $affectedGuarantors
     * @param  array<string, array<int, string>>  $deceasedGuarantors
     * @param  array<string, array<int, array{filename: ?string, url: ?string, source_index: ?int}>>  $attachments
     * @param  array<string, array<int, array{head_name: string, id_number: ?string, members_count: ?int, phone: ?string, employment_status: ?string, source_index: ?int}>>  $residentHouseholds
     * @return array<int, array<string, mixed>>
     */
    private function sourceRowsFromWorksheet(array $rows, array $headers, array $affectedGuarantors, array $deceasedGuarantors, array $attachments = [], array $residentHouseholds = []): array
    {
        $sourceRows = [];

        foreach ($rows as $index => $row) {
            if (collect($row)->filter(fn (mixed $value): bool => $this->text($value) !== '')->isEmpty()) {
                continue;
            }

            $uuid = $this->value($row, $headers, '_uuid');
            $sourceRows[] = [
                'row_number' => $index + 2,
                'source_uuid' => $uuid,
                'source_submission_id' => $this->value($row, $headers, '_id'),
                'submitted_by_name' => $this->value($row, $headers, 'اسم الموظف/ة'),
                'surveyed_at' => $this->value($row, $headers, 'التاريخ والوقت'),
                'location_latitude' => $this->value($row, $headers, '_الاحداثيات_latitude'),
                'location_longitude' => $this->value($row, $headers, '_الاحداثيات_longitude'),
                'location_altitude' => $this->value($row, $headers, '_الاحداثيات_altitude'),
                'location_precision' => $this->value($row, $headers, '_الاحداثيات_precision'),
                'form_number' => $this->value($row, $headers, 'رقم الاستمارة'),
                'borrower_name' => $this->value($row, $headers, 'اسم المقترض/ة رباعي:'),
                'borrower_id_number' => $this->value($row, $headers, 'رقم هوية المقترض/ة:'),
                'family_members_count' => $this->value($row, $headers, 'عدد افراد أسرته/ها:'),
                'marital_status_label' => $this->value($row, $headers, 'الحالة الاجنماعية للمقترض/ة:'),
                'spouse_name' => $this->value($row, $headers, 'اسم الزوج/ة:'),
                'spouse_id_number' => $this->value($row, $headers, 'رقم هوية الزوج/ة:'),
                'employment_status_label' => $this->value($row, $headers, 'الوضع الوظيفي(للمقترض/ة)'),
                'alive_label' => $this->value($row, $headers, 'هل المقترض/ة على قيد الحياة؟'),
                'vulnerability_types' => $this->checkboxValues($row, $headers, [
                    'وجود شهداء او مصابين او ذوي الاعاقات في عائلة المقترض/ة؟/يوجد شهداء' => 'martyrs',
                    'وجود شهداء او مصابين او ذوي الاعاقات في عائلة المقترض/ة؟/يوجد مصابين' => 'injured',
                    'وجود شهداء او مصابين او ذوي الاعاقات في عائلة المقترض/ة؟/يوجد اشخاص ذوي اعاقة' => 'disabled',
                    'وجود شهداء او مصابين او ذوي الاعاقات في عائلة المقترض/ة؟/يوجد كبار سن في العائلة' => 'elderly',
                    'وجود شهداء او مصابين او ذوي الاعاقات في عائلة المقترض/ة؟/ليس مما سبق' => 'none',
                ]),
                'guarantors_count' => $this->value($row, $headers, 'عدد الكفلاء للقرض'),
                'guarantors_alive_label' => $this->value($row, $headers, 'هل جميع الكفلاء على قيد الحياة ؟'),
                'guarantors_employment_statuses' => $this->checkboxValues($row, $headers, [
                    'الوضع الوظيفي للكفلاء/جميعهم على رأس عملهم' => 'all_working',
                    'الوضع الوظيفي للكفلاء/يوجد كفيل متقاعد' => 'retired',
                    'الوضع الوظيفي للكفلاء/يوجد كفيل فقد عمله' => 'lost_job',
                ]),
                'affected_guarantor_names' => $affectedGuarantors[$uuid] ?? [],
                'deceased_guarantor_names' => $deceasedGuarantors[$uuid] ?? [],
                'attachments' => $attachments[$uuid] ?? [],
                'resident_households' => $residentHouseholds[$uuid] ?? [],
                'displacement_status_label' => $this->value($row, $headers, 'حالة النزوح الحالية (تستهدف حالة المستفيد وعائلته ولا علاقة له بالوحدة السكنية المستهدفة بالقرض)'),
                'displaced_to_governorate_label' => $this->value($row, $headers, 'المحافظة النازح اليها (المستفيد)'),
                'current_residence_address' => $this->value($row, $headers, 'عنوان السكن الحالي للمقترض او النازح اليه'),
                'phone_primary' => $this->value($row, $headers, 'رقم التواصل 1'),
                'phone_secondary' => $this->value($row, $headers, 'رقم التواصل 2'),
                'loan_unit_address' => $this->value($row, $headers, 'عنوان الوحدة السكنية المستهدفة بالقرض( المحافظة- المدينة- أقرب معلم)'),
                'loan_unit_area' => $this->value($row, $headers, 'مساحة الوحدة السكنية م2'),
                'loan_unit_floor_type_label' => $this->value($row, $headers, 'الطابق'),
                'parcel_number' => $this->value($row, $headers, 'رقم القطعة'),
                'plot_number' => $this->value($row, $headers, 'رقم القسيمة'),
                'loan_unit_occupancy_label' => $this->value($row, $headers, 'وضع  الاشخاص الذين يعيشون داخل الشقة المستهدفة بالقرض'),
                'loan_unit_damage_label' => $this->value($row, $headers, 'الوضع الانشائي للوحدة السكنية المستهدفة بالقرض'),
                'notes' => $this->values($row, $headers, 'اضافة ملاحظات'),
                'boq_quantities' => $this->boqQuantities($row, $headers),
            ];
        }

        return $sourceRows;
    }

    /**
     * @param  array<string, mixed>  $sourceRow
     * @return array<string, mixed>
     */
    private function sourceRow(array $sourceRow): array
    {
        return [
            'row_number' => (int) ($sourceRow['row_number'] ?? 0),
            'source_uuid' => $this->text($sourceRow['source_uuid'] ?? null),
            'source_submission_id' => $this->integer($this->text($sourceRow['source_submission_id'] ?? null)),
            'submitted_by_name' => $this->text($sourceRow['submitted_by_name'] ?? null),
            'surveyed_at' => $this->dateValue($this->text($sourceRow['surveyed_at'] ?? null)),
            'location_latitude' => $this->decimal($this->text($sourceRow['location_latitude'] ?? null)),
            'location_longitude' => $this->decimal($this->text($sourceRow['location_longitude'] ?? null)),
            'location_altitude' => $this->decimal($this->text($sourceRow['location_altitude'] ?? null)),
            'location_precision' => $this->decimal($this->text($sourceRow['location_precision'] ?? null)),
            'form_number' => $this->text($sourceRow['form_number'] ?? null),
            'borrower_name' => $this->text($sourceRow['borrower_name'] ?? null),
            'borrower_id_number' => $this->text($sourceRow['borrower_id_number'] ?? null),
            'family_members_count' => $this->integer($this->text($sourceRow['family_members_count'] ?? null)),
            'marital_status_label' => $this->text($sourceRow['marital_status_label'] ?? null),
            'spouse_name' => $this->text($sourceRow['spouse_name'] ?? null),
            'spouse_id_number' => $this->text($sourceRow['spouse_id_number'] ?? null),
            'employment_status_label' => $this->text($sourceRow['employment_status_label'] ?? null),
            'alive_label' => $this->text($sourceRow['alive_label'] ?? null),
            'vulnerability_types' => array_values(array_filter((array) ($sourceRow['vulnerability_types'] ?? []), 'is_string')),
            'guarantors_count' => $this->integer($this->text($sourceRow['guarantors_count'] ?? null)),
            'guarantors_alive_label' => $this->text($sourceRow['guarantors_alive_label'] ?? null),
            'guarantors_employment_statuses' => array_values(array_filter((array) ($sourceRow['guarantors_employment_statuses'] ?? []), 'is_string')),
            'affected_guarantor_names' => array_values(array_filter((array) ($sourceRow['affected_guarantor_names'] ?? []), 'is_string')),
            'deceased_guarantor_names' => array_values(array_filter((array) ($sourceRow['deceased_guarantor_names'] ?? []), 'is_string')),
            'attachments' => array_values(array_filter((array) ($sourceRow['attachments'] ?? []), 'is_array')),
            'resident_households' => array_values(array_filter((array) ($sourceRow['resident_households'] ?? []), 'is_array')),
            'displacement_status_label' => $this->text($sourceRow['displacement_status_label'] ?? null),
            'displaced_to_governorate_label' => $this->text($sourceRow['displaced_to_governorate_label'] ?? null),
            'current_residence_address' => $this->text($sourceRow['current_residence_address'] ?? null),
            'phone_primary' => $this->text($sourceRow['phone_primary'] ?? null),
            'phone_secondary' => $this->text($sourceRow['phone_secondary'] ?? null),
            'loan_unit_address' => $this->text($sourceRow['loan_unit_address'] ?? null),
            'loan_unit_area' => $this->decimal($this->text($sourceRow['loan_unit_area'] ?? null)),
            'loan_unit_floor_type_label' => $this->text($sourceRow['loan_unit_floor_type_label'] ?? ($sourceRow['loan_unit_floor_type'] ?? null)),
            'parcel_number' => $this->text($sourceRow['parcel_number'] ?? null),
            'plot_number' => $this->text($sourceRow['plot_number'] ?? null),
            'loan_unit_occupancy_label' => $this->text($sourceRow['loan_unit_occupancy_label'] ?? null),
            'loan_unit_damage_label' => $this->text($sourceRow['loan_unit_damage_label'] ?? null),
            'notes' => array_values(array_filter((array) ($sourceRow['notes'] ?? []), 'is_string')),
            'boq_quantities' => array_values(array_filter((array) ($sourceRow['boq_quantities'] ?? []), 'is_array')),
        ];
    }

    /**
     * @param  array<string, mixed>  $sourceRow
     * @return array<int, string>
     */
    private function validateMappedRow(array $sourceRow): array
    {
        $reasons = [];

        foreach (['source_uuid' => 'معرف المصدر', 'borrower_name' => 'اسم المقترض', 'borrower_id_number' => 'رقم هوية المقترض'] as $field => $label) {
            if ($sourceRow[$field] === '') {
                $reasons[] = "{$label} مفقود.";
            }
        }

        foreach ([
            'employment_status_label' => [self::EMPLOYMENT_MAP, 'الوضع الوظيفي'],
            'alive_label' => [['نعم' => true, 'لا' => false], 'حالة حياة المقترض'],
            'marital_status_label' => [self::MARITAL_MAP, 'الحالة الاجتماعية'],
            'guarantors_alive_label' => [['نعم' => 'yes', 'لا' => 'no', 'لا يوجد' => 'none'], 'حياة الكفلاء'],
            'displacement_status_label' => [self::DISPLACEMENT_MAP, 'حالة النزوح'],
            'displaced_to_governorate_label' => [self::GOVERNORATE_MAP, 'محافظة النزوح'],
            'loan_unit_occupancy_label' => [self::OCCUPANCY_MAP, 'إشغال الوحدة'],
            'loan_unit_damage_label' => [self::DAMAGE_MAP, 'الوضع الإنشائي'],
        ] as $field => [$allowed, $label]) {
            if ($sourceRow[$field] !== '' && ! array_key_exists($sourceRow[$field], $allowed)) {
                $reasons[] = "{$label} يحتوي قيمة غير معروفة.";
            }
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $sourceRow
     * @return array<string, mixed>
     */
    private function mappedData(array $sourceRow): array
    {
        $affectedStatus = count($sourceRow['guarantors_employment_statuses']) === 1
            ? $sourceRow['guarantors_employment_statuses'][0]
            : null;
        $boqItems = $this->mappedBoqItems($sourceRow['boq_quantities']);
        $loanUnitDamageStatus = self::DAMAGE_MAP[$sourceRow['loan_unit_damage_label']] ?? null;
        $loanUnitFloorType = $this->damageValuation->normalizeFloorType($sourceRow['loan_unit_floor_type_label']);
        $boqTotalUsd = $this->damageValuation->fullDemolitionValueUsd(
            $sourceRow['loan_unit_area'],
            $loanUnitFloorType,
            $loanUnitDamageStatus,
        ) ?? collect($boqItems)->sum('total_price');
        $exchangeRate = $this->currentExchangeRate();

        return [
            'submitted_by_name' => $sourceRow['submitted_by_name'] ?: null,
            'source_uuid' => $sourceRow['source_uuid'],
            'source_submission_id' => $sourceRow['source_submission_id'],
            'surveyed_at' => $sourceRow['surveyed_at'],
            'location_latitude' => $sourceRow['location_latitude'],
            'location_longitude' => $sourceRow['location_longitude'],
            'location_altitude' => $sourceRow['location_altitude'],
            'location_precision' => $sourceRow['location_precision'],
            'form_number' => $sourceRow['form_number'] ?: null,
            'borrower_name' => $sourceRow['borrower_name'],
            'borrower_id_number' => $sourceRow['borrower_id_number'],
            'family_members_count' => $sourceRow['family_members_count'],
            'marital_status' => self::MARITAL_MAP[$sourceRow['marital_status_label']] ?? null,
            'spouse_name' => $sourceRow['spouse_name'] ?: null,
            'spouse_id_number' => $sourceRow['spouse_id_number'] ?: null,
            'employment_status' => self::EMPLOYMENT_MAP[$sourceRow['employment_status_label']] ?? null,
            'is_borrower_alive' => $sourceRow['alive_label'] === 'نعم',
            'vulnerability_types' => $sourceRow['vulnerability_types'],
            'guarantors_count' => $sourceRow['guarantors_count'],
            'guarantors_alive_status' => ['نعم' => 'yes', 'لا' => 'no', 'لا يوجد' => 'none'][$sourceRow['guarantors_alive_label']] ?? null,
            'deceased_guarantors' => collect($sourceRow['deceased_guarantor_names'])->map(fn (string $name): array => ['name' => $name])->all(),
            'guarantors_employment_statuses' => $sourceRow['guarantors_employment_statuses'],
            'affected_guarantors' => collect($sourceRow['affected_guarantor_names'])->map(fn (string $name): array => ['name' => $name, 'status' => $affectedStatus])->all(),
            'displacement_status' => self::DISPLACEMENT_MAP[$sourceRow['displacement_status_label']] ?? null,
            'displaced_to_governorate' => self::GOVERNORATE_MAP[$sourceRow['displaced_to_governorate_label']] ?? null,
            'current_residence_address' => $sourceRow['current_residence_address'] ?: null,
            'phone_primary' => $sourceRow['phone_primary'] ?: null,
            'phone_secondary' => $sourceRow['phone_secondary'] ?: null,
            'loan_unit_address' => $sourceRow['loan_unit_address'] ?: null,
            'loan_unit_area' => $sourceRow['loan_unit_area'],
            'loan_unit_floor_type' => $loanUnitFloorType,
            'parcel_number' => $sourceRow['parcel_number'] ?: null,
            'plot_number' => $sourceRow['plot_number'] ?: null,
            'loan_unit_occupancy_status' => self::OCCUPANCY_MAP[$sourceRow['loan_unit_occupancy_label']] ?? null,
            'loan_unit_damage_status' => $loanUnitDamageStatus,
            'notes' => collect($sourceRow['notes'])->filter()->unique()->implode("\n") ?: null,
            'resident_households' => $sourceRow['resident_households'],
            'attachments' => $sourceRow['attachments'],
            'boq_items' => $boqItems,
            'boq_total_usd' => $boqTotalUsd,
            'exchange_rate' => $exchangeRate,
            'boq_total_ils' => round($boqTotalUsd * $exchangeRate, 2),
            'attachments_count' => count($sourceRow['attachments']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveImportedBorrower(array $data): DamageAssessmentBorrower
    {
        $borrower = null;

        if (($data['source_uuid'] ?? '') !== '') {
            $borrower = DamageAssessmentBorrower::query()
                ->where('source_uuid', $data['source_uuid'])
                ->first();
        }

        if (! $borrower instanceof DamageAssessmentBorrower) {
            $borrower = DamageAssessmentBorrower::query()
                ->where('borrower_id_number', $data['borrower_id_number'])
                ->firstOrNew();
        }

        $borrower->fill($data);
        $borrower->save();

        return $borrower;
    }

    /**
     * @param  array<int, array{source_column: string, quantity: float, sort_order: int}>  $quantities
     * @return array<int, array<string, mixed>>
     */
    private function mappedBoqItems(array $quantities): array
    {
        if ($quantities === []) {
            return [];
        }

        $catalogItems = Schema::hasTable('damage_assessment_borrower_boq_catalog_items')
            ? BorrowerBoqCatalogItem::query()->orderBy('sort_order')->get()
            : collect();

        return collect($quantities)
            ->map(function (array $quantity) use ($catalogItems): array {
                $description = $quantity['source_column'];
                $normalizedDescription = $this->normalizeDescription($description);
                $catalogItem = $catalogItems->first(function (BorrowerBoqCatalogItem $catalogItem) use ($normalizedDescription): bool {
                    $catalogDescription = (string) $catalogItem->normalized_description;

                    return $catalogDescription !== ''
                        && (str_contains($normalizedDescription, $catalogDescription)
                            || str_contains($catalogDescription, mb_substr($normalizedDescription, 0, 120)));
                });
                $unitPrice = (float) ($catalogItem?->unit_price ?? 0);
                $exchangeRate = $this->currentExchangeRate();
                $itemQuantity = (float) $quantity['quantity'];

                return [
                    'catalog_item_id' => $catalogItem?->id,
                    'source_column' => $description,
                    'source_key' => sha1($description),
                    'item_code' => $catalogItem?->item_code,
                    'description' => $catalogItem?->description ?? $description,
                    'unit' => $catalogItem?->unit ?? $this->unitFromDescription($description),
                    'unit_price' => $unitPrice,
                    'exchange_rate' => $exchangeRate,
                    'unit_price_ils' => round($unitPrice * $exchangeRate, 2),
                    'quantity' => $itemQuantity,
                    'total_price' => round($itemQuantity * $unitPrice, 2),
                    'total_price_ils' => round($itemQuantity * $unitPrice * $exchangeRate, 2),
                    'sort_order' => $quantity['sort_order'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $boqItems
     */
    private function syncBoqItems(DamageAssessmentBorrower $borrower, array $boqItems): void
    {
        $seenKeys = [];

        foreach ($boqItems as $boqItem) {
            $seenKeys[] = $boqItem['source_key'];
            $borrower->boqItems()->updateOrCreate(
                ['source_key' => $boqItem['source_key']],
                $boqItem
            );
        }

        $borrower->boqItems()
            ->when($seenKeys !== [], fn ($query) => $query->whereNotIn('source_key', $seenKeys))
            ->when($seenKeys === [], fn ($query) => $query)
            ->delete();
    }

    /**
     * @param  array<int, array{filename: ?string, url: ?string, source_index: ?int}>  $attachments
     */
    private function syncAttachments(DamageAssessmentBorrower $borrower, array $attachments): void
    {
        $seenIndexes = [];

        foreach ($attachments as $attachment) {
            $sourceIndex = $attachment['source_index'] ?? count($seenIndexes) + 1;
            $seenIndexes[] = $sourceIndex;
            $borrower->attachments()->updateOrCreate(
                ['source_index' => $sourceIndex],
                [
                    'filename' => $attachment['filename'] ?? null,
                    'url' => $attachment['url'] ?? null,
                    'source_index' => $sourceIndex,
                ]
            );
        }

        $borrower->attachments()
            ->when($seenIndexes !== [], fn ($query) => $query->whereNotIn('source_index', $seenIndexes))
            ->when($seenIndexes === [], fn ($query) => $query)
            ->delete();
    }

    /**
     * @param  array<int, array{head_name: string, id_number: ?string, members_count: ?int, phone: ?string, employment_status: ?string, source_index: ?int}>  $households
     */
    private function syncResidentHouseholds(DamageAssessmentBorrower $borrower, array $households): void
    {
        $seenIndexes = [];

        foreach ($households as $household) {
            $sourceIndex = $household['source_index'] ?? count($seenIndexes) + 1;
            $seenIndexes[] = $sourceIndex;
            $borrower->residentHouseholds()->updateOrCreate(
                ['source_index' => $sourceIndex],
                array_merge($household, ['source_index' => $sourceIndex])
            );
        }

        $borrower->residentHouseholds()
            ->when($seenIndexes !== [], fn ($query) => $query->whereNotIn('source_index', $seenIndexes))
            ->when($seenIndexes === [], fn ($query) => $query)
            ->delete();
    }

    private function loanSheetStatus(string $sheetName): ?string
    {
        return match ($sheetName) {
            'نشطه' => 'active',
            'مغلقه' => 'closed',
            default => null,
        };
    }

    /**
     * @return array<int, array{row_number: int, loan_number: string, loan_status: string, borrower_id_number: string, borrower_name: string, phone_primary: string, address: string, loan_original_amount: ?float, loan_total_amount: ?float, loan_portfolio_amount: ?float, loan_net_amount: ?float, loan_balance: ?float, loan_paid_amount: ?float, loan_installments_count: ?int, loan_started_at: ?string, loan_last_installment_at: ?string, loan_clearance_delivered: ?bool}>
     */
    private function loanRows(Worksheet $sheet): array
    {
        $isActive = $this->loanSheetStatus($sheet->getTitle()) === 'active';
        $startRow = $isActive ? 3 : 7;
        $rows = $sheet->toArray(null, true, true, false);
        $loanRows = [];

        foreach (array_slice($rows, $startRow - 1) as $index => $row) {
            $loanNumber = $this->text($row[1] ?? null);
            $borrowerId = $this->text($row[2] ?? null);
            $borrowerName = $this->text($row[$isActive ? 5 : 3] ?? null);

            if ($loanNumber === '' && $borrowerId === '' && $borrowerName === '') {
                continue;
            }

            $loanRows[] = [
                'row_number' => $startRow + $index,
                'loan_number' => $loanNumber,
                'loan_status' => $isActive ? 'active' : 'closed',
                'borrower_id_number' => $borrowerId,
                'borrower_name' => $borrowerName,
                'phone_primary' => $this->text($row[$isActive ? 4 : 5] ?? null),
                'address' => $this->text($row[$isActive ? 6 : 6] ?? null),
                'loan_original_amount' => $this->decimal($this->text($row[$isActive ? 3 : 4] ?? null)),
                'loan_total_amount' => $this->decimal($this->text($row[$isActive ? 9 : 7] ?? null)),
                'loan_portfolio_amount' => $isActive ? $this->decimal($this->text($row[10] ?? null)) : null,
                'loan_net_amount' => $isActive ? $this->decimal($this->text($row[11] ?? null)) : null,
                'loan_balance' => $this->decimal($this->text($row[$isActive ? 12 : 12] ?? null)),
                'loan_paid_amount' => $isActive ? null : $this->decimal($this->text($row[11] ?? null)),
                'loan_installments_count' => $isActive ? null : $this->integer($this->text($row[8] ?? null)),
                'loan_started_at' => $isActive ? null : $this->dateValue($this->text($row[9] ?? null)),
                'loan_last_installment_at' => $isActive ? $this->dateValue($this->text($row[8] ?? null)) : null,
                'loan_clearance_delivered' => $isActive ? null : $this->text($row[13] ?? null) === '1',
            ];
        }

        return $loanRows;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function repeatedNamesByUuid(Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, false);
        $headers = $this->headers(array_shift($rows) ?? []);
        $namesByUuid = [];

        foreach ($rows as $row) {
            $uuid = $this->value($row, $headers, '_submission__uuid');
            $name = collect($row)
                ->map(fn (mixed $value): string => $this->text($value))
                ->first(fn (string $value, int $index): bool => str_starts_with($this->headerAt($headers, $index), 'تحديد اسم الكفيل') && $value !== '');

            if ($uuid !== '' && is_string($name) && $name !== '') {
                $namesByUuid[$uuid][] = $name;
            }
        }

        return $namesByUuid;
    }

    /**
     * @return array<string, array<int, array{filename: ?string, url: ?string, source_index: ?int}>>
     */
    private function attachmentsByUuid(?Worksheet $sheet): array
    {
        if (! $sheet instanceof Worksheet) {
            return [];
        }

        $rows = $sheet->toArray(null, true, true, false);
        $headers = $this->headers(array_shift($rows) ?? []);
        $attachmentsByUuid = [];

        foreach ($rows as $row) {
            $uuid = $this->value($row, $headers, '_submission__uuid');
            $filename = $this->text($row[0] ?? null);
            $url = $this->text($row[1] ?? null);

            if ($uuid === '' || ($filename === '' && $url === '')) {
                continue;
            }

            $attachmentsByUuid[$uuid][] = [
                'filename' => $filename ?: null,
                'url' => $url ?: null,
                'source_index' => $this->integer($this->value($row, $headers, '_index')),
            ];
        }

        return $attachmentsByUuid;
    }

    /**
     * @return array<string, array<int, array{head_name: string, id_number: ?string, members_count: ?int, phone: ?string, employment_status: ?string, source_index: ?int}>>
     */
    private function residentHouseholdsByUuid(?Worksheet $sheet): array
    {
        if (! $sheet instanceof Worksheet) {
            return [];
        }

        $rows = $sheet->toArray(null, true, true, false);
        $headers = $this->headers(array_shift($rows) ?? []);
        $householdsByUuid = [];

        foreach ($rows as $row) {
            $uuid = $this->value($row, $headers, '_submission__uuid');
            $headName = $this->text($row[0] ?? null);

            if ($uuid === '' || $headName === '') {
                continue;
            }

            $employmentLabel = $this->text($row[4] ?? null);
            $householdsByUuid[$uuid][] = [
                'head_name' => $headName,
                'id_number' => $this->text($row[1] ?? null) ?: null,
                'members_count' => $this->integer($this->text($row[2] ?? null)),
                'phone' => $this->text($row[3] ?? null) ?: null,
                'employment_status' => self::EMPLOYMENT_MAP[$employmentLabel] ?? $employmentLabel ?: null,
                'source_index' => $this->integer($this->value($row, $headers, '_index')),
            ];
        }

        return $householdsByUuid;
    }

    /**
     * @param  array<string, array<int, int>>  $headers
     */
    private function headerAt(array $headers, int $index): string
    {
        foreach ($headers as $heading => $indexes) {
            if (in_array($index, $indexes, true)) {
                return $heading;
            }
        }

        return '';
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, array<int, int>>  $headers
     * @param  array<string, string>  $options
     * @return array<int, string>
     */
    private function checkboxValues(array $row, array $headers, array $options): array
    {
        $selected = [];

        foreach ($options as $header => $value) {
            if ($this->truthy($this->value($row, $headers, $header))) {
                $selected[] = $value;
            }
        }

        return $selected;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, array<int, int>>  $headers
     */
    private function value(array $row, array $headers, string $header): string
    {
        return $this->values($row, $headers, $header)[0] ?? '';
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, array<int, int>>  $headers
     * @return array<int, string>
     */
    private function values(array $row, array $headers, string $header): array
    {
        return collect($headers[$header] ?? [])
            ->map(fn (int $index): string => $this->text($row[$index] ?? null))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, array<int, int>>  $headers
     * @return array<int, array{source_column: string, quantity: float, sort_order: int}>
     */
    private function boqQuantities(array $row, array $headers): array
    {
        $quantities = [];

        foreach ($headers as $header => $indexes) {
            if (! $this->looksLikeBoqHeader($header)) {
                continue;
            }

            foreach ($indexes as $index) {
                $quantity = $this->decimal($this->text($row[$index] ?? null));

                if ($quantity === null || $quantity <= 0) {
                    continue;
                }

                $quantities[] = [
                    'source_column' => $header,
                    'quantity' => $quantity,
                    'sort_order' => $index + 1,
                ];
            }
        }

        return $quantities;
    }

    private function looksLikeBoqHeader(string $header): bool
    {
        if (str_starts_with($header, '_') || mb_strlen($header) < 30) {
            return false;
        }

        foreach (['م2', 'م3', 'عدد', 'م.ط', 'مقطوعية'] as $unit) {
            if (str_contains($header, $unit)) {
                return true;
            }
        }

        return str_contains($header, 'شبكة كهرباء');
    }

    private function normalizeDescription(string $description): string
    {
        $description = mb_strtolower($description);
        $description = preg_replace('/\([^)]*\)/u', ' ', $description) ?? $description;
        $description = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', ' ', $description) ?? $description;

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $description) ?? $description), 0, 255);
    }

    private function unitFromDescription(string $description): ?string
    {
        foreach (['م2', 'م3', 'عدد', 'م.ط', 'مقطوعية'] as $unit) {
            if (str_contains($description, $unit)) {
                return $unit;
            }
        }

        return null;
    }

    private function currentExchangeRate(): float
    {
        if (Schema::hasTable('damage_assessment_borrower_pricing_settings')) {
            $exchangeRate = BorrowerPricingSetting::query()->value('exchange_rate');

            if ($exchangeRate !== null) {
                return (float) $exchangeRate;
            }
        }

        if (! Schema::hasColumn('damage_assessment_borrower_boq_catalog_items', 'unit_price_ils')) {
            return 3.2;
        }

        $catalogItem = BorrowerBoqCatalogItem::query()
            ->where('unit_price', '>', 0)
            ->where('unit_price_ils', '>', 0)
            ->first();

        if (! $catalogItem instanceof BorrowerBoqCatalogItem) {
            return 3.2;
        }

        return round((float) $catalogItem->unit_price_ils / (float) $catalogItem->unit_price, 4);
    }

    private function truthy(string $value): bool
    {
        return in_array($value, ['1', 'نعم', 'true'], true);
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function integer(string $value): ?int
    {
        $normalizedValue = $this->normalizedNumber($value);

        return $normalizedValue === null ? null : (int) $normalizedValue;
    }

    private function decimal(string $value): ?float
    {
        $normalizedValue = $this->normalizedNumber($value);

        return $normalizedValue === null ? null : (float) $normalizedValue;
    }

    private function normalizedNumber(string $value): ?string
    {
        $normalizedValue = str_replace([',', '٬', ' '], '', trim($value));

        return $normalizedValue === '' || ! is_numeric($normalizedValue) ? null : $normalizedValue;
    }

    private function dateValue(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', strtotime($value));
    }
}
