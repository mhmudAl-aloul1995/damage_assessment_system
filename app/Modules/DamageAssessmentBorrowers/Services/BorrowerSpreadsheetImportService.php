<?php

namespace App\Modules\DamageAssessmentBorrowers\Services;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Illuminate\Support\Facades\DB;
use JsonException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
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

    public function __construct(private readonly BorrowerRiskAnalysisService $riskAnalysis) {}

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

            if ($this->conflictsWithExistingIdentity($sourceRow)) {
                $reasons[] = 'رقم هوية المقترض موجود مسبقاً في النظام لسجل آخر.';
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
                    $borrower = DamageAssessmentBorrower::query()->updateOrCreate(
                        ['source_uuid' => $data['source_uuid']],
                        $data
                    );

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
            'displacement_status_label' => $this->text($sourceRow['displacement_status_label'] ?? null),
            'displaced_to_governorate_label' => $this->text($sourceRow['displaced_to_governorate_label'] ?? null),
            'current_residence_address' => $this->text($sourceRow['current_residence_address'] ?? null),
            'phone_primary' => $this->text($sourceRow['phone_primary'] ?? null),
            'phone_secondary' => $this->text($sourceRow['phone_secondary'] ?? null),
            'loan_unit_address' => $this->text($sourceRow['loan_unit_address'] ?? null),
            'loan_unit_area' => $this->decimal($this->text($sourceRow['loan_unit_area'] ?? null)),
            'parcel_number' => $this->text($sourceRow['parcel_number'] ?? null),
            'plot_number' => $this->text($sourceRow['plot_number'] ?? null),
            'loan_unit_occupancy_label' => $this->text($sourceRow['loan_unit_occupancy_label'] ?? null),
            'loan_unit_damage_label' => $this->text($sourceRow['loan_unit_damage_label'] ?? null),
            'notes' => array_values(array_filter((array) ($sourceRow['notes'] ?? []), 'is_string')),
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
     */
    private function conflictsWithExistingIdentity(array $sourceRow): bool
    {
        if ($sourceRow['borrower_id_number'] === '' || $sourceRow['source_uuid'] === '') {
            return false;
        }

        return DamageAssessmentBorrower::query()
            ->where('borrower_id_number', $sourceRow['borrower_id_number'])
            ->where(function ($query) use ($sourceRow): void {
                $query->whereNull('source_uuid')
                    ->orWhere('source_uuid', '!=', $sourceRow['source_uuid']);
            })
            ->exists();
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

        return [
            'submitted_by_name' => $sourceRow['submitted_by_name'] ?: null,
            'source_uuid' => $sourceRow['source_uuid'],
            'source_submission_id' => $sourceRow['source_submission_id'],
            'surveyed_at' => $sourceRow['surveyed_at'],
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
            'parcel_number' => $sourceRow['parcel_number'] ?: null,
            'plot_number' => $sourceRow['plot_number'] ?: null,
            'loan_unit_occupancy_status' => self::OCCUPANCY_MAP[$sourceRow['loan_unit_occupancy_label']] ?? null,
            'loan_unit_damage_status' => self::DAMAGE_MAP[$sourceRow['loan_unit_damage_label']] ?? null,
            'notes' => collect($sourceRow['notes'])->filter()->unique()->implode("\n") ?: null,
        ];
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function integer(string $value): ?int
    {
        return $value === '' || ! is_numeric($value) ? null : (int) $value;
    }

    private function decimal(string $value): ?float
    {
        return $value === '' || ! is_numeric($value) ? null : (float) $value;
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
