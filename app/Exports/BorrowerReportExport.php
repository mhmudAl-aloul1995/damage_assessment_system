<?php

namespace App\Exports;

use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BorrowerReportExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    private const BOQ_HORIZONTAL_COLUMN = 'boq_items_horizontal';

    /**
     * @param  \Illuminate\Support\Collection<int, DamageAssessmentBorrower>  $borrowers
     * @param  array<int, string>  $selectedColumns
     */
    public function __construct(
        private readonly \Illuminate\Support\Collection $borrowers,
        private readonly string $reportType = 'compact',
        private readonly array $selectedColumns = [],
    ) {}

    public function collection(): \Illuminate\Support\Collection
    {
        return $this->borrowers;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        if ($this->reportType === 'custom') {
            $headings = collect($this->customColumnKeys())
                ->map(fn (string $key): string => self::availableColumns()[$key]['label'])
                ->values()
                ->all();

            if ($this->includesHorizontalBoq()) {
                $maxBoqItemsCount = $this->maxBoqItemsCount();

                for ($index = 1; $index <= $maxBoqItemsCount; $index++) {
                    $prefix = 'BOQ '.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
                    array_push(
                        $headings,
                        "{$prefix} - كود البند",
                        "{$prefix} - وصف البند",
                        "{$prefix} - الوحدة",
                        "{$prefix} - سعر الوحدة $",
                        "{$prefix} - سعر الوحدة ILS",
                        "{$prefix} - الكمية",
                        "{$prefix} - الإجمالي $",
                        "{$prefix} - الإجمالي ILS",
                    );
                }
            }

            return $headings;
        }

        if ($this->reportType === 'detailed') {
            return [
                'الكود',
                'كود المستفيد',
                'اسم المقترض',
                'رقم الهوية',
                'رقم القرض',
                'قيمة القرض',
                'المبلغ المتبقي',
                'المساحة',
                'الطابق',
                'سعر المتر',
                'قيمة الضرر للهدم الكلي',
                'قيمة الضرر بالشيكل',
                'نوع الضرر',
                'درجة الخطورة',
                'عدد الصور',
                'تاريخ المسح',
                'الملاحظات',
            ];
        }

        return [
            'الكود',
            'كود المستفيد',
            'اسم المقترض',
            'رقم الهوية',
            'قيمة القرض',
            'المبلغ المتبقي',
            'قيمة الضرر للهدم الكلي',
            'نوع الضرر',
            'الملاحظات',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        if ($this->reportType === 'custom') {
            $mapped = collect($this->customColumnKeys())
                ->map(fn (string $key): mixed => $this->customColumnValue($row, $key))
                ->values()
                ->all();

            if ($this->includesHorizontalBoq()) {
                $boqItems = $row->boqItems
                    ->filter(fn ($item): bool => (float) $item->quantity > 0)
                    ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                    ->values();

                $maxBoqItemsCount = $this->maxBoqItemsCount();

                for ($index = 0; $index < $maxBoqItemsCount; $index++) {
                    $item = $boqItems->get($index);
                    array_push(
                        $mapped,
                        $item?->item_code,
                        $item?->description,
                        $item?->unit,
                        $this->numberOrNull($item?->unit_price),
                        $this->numberOrNull($item?->unit_price_ils),
                        $this->numberOrNull($item?->quantity),
                        $this->numberOrNull($item?->total_price),
                        $this->numberOrNull($item?->total_price_ils),
                    );
                }
            }

            return $mapped;
        }

        if ($this->reportType === 'detailed') {
            return [
                $this->borrowerCode($row),
                $row->form_number,
                $row->borrower_name,
                $row->borrower_id_number,
                $row->loan_number,
                $this->numberOrNull($row->loan_total_amount),
                $this->numberOrNull($row->loan_balance),
                $this->numberOrNull($row->loan_unit_area),
                $this->optionLabel($row->loan_unit_floor_type),
                $this->fullDemolitionMeterRate($row),
                $this->numberOrNull($row->boq_total_usd),
                $this->numberOrNull($row->boq_total_ils),
                $this->optionLabel($row->loan_unit_damage_status),
                $this->riskLabel($row->risk_level),
                $row->attachments_count,
                $row->surveyed_at?->format('Y-m-d'),
                $row->notes,
            ];
        }

        return [
            $this->borrowerCode($row),
            $row->form_number,
            $row->borrower_name,
            $row->borrower_id_number,
            $this->numberOrNull($row->loan_total_amount),
            $this->numberOrNull($row->loan_balance),
            $this->numberOrNull($row->boq_total_usd),
            $this->optionLabel($row->loan_unit_damage_status),
            $row->notes,
        ];
    }

    public function title(): string
    {
        return 'تقرير المقترضين';
    }

    /**
     * @return array<string, array{label: string, type: string}>
     */
    public static function availableColumns(): array
    {
        return [
            'borrower_code' => ['label' => 'الكود', 'type' => 'text'],
            'form_number' => ['label' => 'كود المستفيد / رقم النموذج', 'type' => 'text'],
            'source_uuid' => ['label' => 'مصدر الإدخال UUID', 'type' => 'text'],
            'source_submission_id' => ['label' => 'رقم إدخال KoBo', 'type' => 'number'],
            'borrower_name' => ['label' => 'اسم المقترض', 'type' => 'text'],
            'borrower_id_number' => ['label' => 'رقم الهوية', 'type' => 'text'],
            'phone_primary' => ['label' => 'الجوال الأساسي', 'type' => 'text'],
            'phone_secondary' => ['label' => 'الجوال الثانوي', 'type' => 'text'],
            'submitted_by_name' => ['label' => 'اسم مدخل البيانات', 'type' => 'text'],
            'surveyed_at' => ['label' => 'تاريخ المسح', 'type' => 'date'],
            'created_at' => ['label' => 'تاريخ إنشاء السجل', 'type' => 'date'],
            'updated_at' => ['label' => 'تاريخ آخر تحديث', 'type' => 'date'],
            'loan_number' => ['label' => 'رقم القرض', 'type' => 'text'],
            'loan_status' => ['label' => 'حالة القرض', 'type' => 'text'],
            'loan_original_amount' => ['label' => 'أصل القرض', 'type' => 'number'],
            'loan_total_amount' => ['label' => 'قيمة القرض', 'type' => 'number'],
            'loan_portfolio_amount' => ['label' => 'محفظة القرض', 'type' => 'number'],
            'loan_net_amount' => ['label' => 'صافي القرض', 'type' => 'number'],
            'loan_balance' => ['label' => 'المبلغ المتبقي', 'type' => 'number'],
            'loan_paid_amount' => ['label' => 'المبلغ المدفوع', 'type' => 'number'],
            'loan_installments_count' => ['label' => 'عدد دفعات السداد', 'type' => 'number'],
            'loan_started_at' => ['label' => 'تاريخ بداية السداد', 'type' => 'date'],
            'loan_last_installment_at' => ['label' => 'تاريخ آخر قسط', 'type' => 'date'],
            'loan_clearance_delivered' => ['label' => 'سُلّمت براءة الذمة', 'type' => 'text'],
            'family_members_count' => ['label' => 'عدد أفراد الأسرة', 'type' => 'number'],
            'marital_status' => ['label' => 'الحالة الاجتماعية', 'type' => 'text'],
            'spouse_name' => ['label' => 'اسم الزوج/الزوجة', 'type' => 'text'],
            'spouse_id_number' => ['label' => 'هوية الزوج/الزوجة', 'type' => 'text'],
            'employment_status' => ['label' => 'حالة العمل', 'type' => 'text'],
            'is_borrower_alive' => ['label' => 'المقترض على قيد الحياة', 'type' => 'text'],
            'vulnerability_types' => ['label' => 'أنواع الهشاشة', 'type' => 'text'],
            'guarantors_count' => ['label' => 'عدد الكفلاء', 'type' => 'number'],
            'guarantors_alive_status' => ['label' => 'حالة حياة الكفلاء', 'type' => 'text'],
            'deceased_guarantors' => ['label' => 'الكفلاء المتوفون', 'type' => 'text'],
            'guarantors_employment_statuses' => ['label' => 'حالات عمل الكفلاء', 'type' => 'text'],
            'affected_guarantors' => ['label' => 'الكفلاء المتضررون', 'type' => 'text'],
            'displacement_status' => ['label' => 'حالة النزوح', 'type' => 'text'],
            'displaced_to_governorate' => ['label' => 'المحافظة الحالية', 'type' => 'text'],
            'current_residence_address' => ['label' => 'عنوان السكن الحالي', 'type' => 'text'],
            'loan_unit_address' => ['label' => 'عنوان وحدة القرض', 'type' => 'text'],
            'loan_unit_area' => ['label' => 'مساحة وحدة القرض', 'type' => 'number'],
            'loan_unit_floor_type' => ['label' => 'نوع الطابق', 'type' => 'text'],
            'parcel_number' => ['label' => 'رقم القطعة', 'type' => 'text'],
            'plot_number' => ['label' => 'رقم القسيمة', 'type' => 'text'],
            'loan_unit_occupancy_status' => ['label' => 'حالة إشغال وحدة القرض', 'type' => 'text'],
            'resident_households' => ['label' => 'الأسر المقيمة', 'type' => 'text'],
            'loan_unit_damage_status' => ['label' => 'حالة ضرر وحدة القرض', 'type' => 'text'],
            'is_inside_yellow_line' => ['label' => 'داخل الخط الأصفر', 'type' => 'text'],
            'location_latitude' => ['label' => 'خط العرض', 'type' => 'number'],
            'location_longitude' => ['label' => 'خط الطول', 'type' => 'number'],
            'location_altitude' => ['label' => 'الارتفاع', 'type' => 'number'],
            'location_precision' => ['label' => 'دقة الموقع', 'type' => 'number'],
            'boq_total_usd' => ['label' => 'قيمة الضرر بالدولار', 'type' => 'number'],
            'exchange_rate' => ['label' => 'سعر الصرف', 'type' => 'number'],
            'boq_total_ils' => ['label' => 'قيمة الضرر بالشيكل', 'type' => 'number'],
            'risk_level' => ['label' => 'مستوى الخطورة', 'type' => 'text'],
            'risk_score' => ['label' => 'درجة الخطورة', 'type' => 'number'],
            'risk_reasons' => ['label' => 'أسباب الخطورة', 'type' => 'text'],
            'attachments_count' => ['label' => 'عدد الصور / المرفقات', 'type' => 'number'],
            'notes' => ['label' => 'الملاحظات', 'type' => 'text'],
            'full_demolition_meter_rate' => ['label' => 'سعر المتر للهدم الكلي', 'type' => 'number'],
            'show_url' => ['label' => 'رابط صفحة المستفيد', 'type' => 'text'],
            'pricing_url' => ['label' => 'رابط صفحة التسعير', 'type' => 'text'],
            self::BOQ_HORIZONTAL_COLUMN => ['label' => 'جدول الكميات BOQ كأعمدة أفقية', 'type' => 'boq'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function availableColumnGroups(): array
    {
        return [
            'بيانات المقترض الأساسية' => [
                'borrower_code',
                'form_number',
                'source_uuid',
                'source_submission_id',
                'borrower_name',
                'borrower_id_number',
                'phone_primary',
                'phone_secondary',
                'submitted_by_name',
                'surveyed_at',
                'created_at',
                'updated_at',
            ],
            'بيانات القرض' => [
                'loan_number',
                'loan_status',
                'loan_original_amount',
                'loan_total_amount',
                'loan_portfolio_amount',
                'loan_net_amount',
                'loan_balance',
                'loan_paid_amount',
                'loan_installments_count',
                'loan_started_at',
                'loan_last_installment_at',
                'loan_clearance_delivered',
            ],
            'بيانات الأسرة والهشاشة' => [
                'family_members_count',
                'marital_status',
                'spouse_name',
                'spouse_id_number',
                'employment_status',
                'is_borrower_alive',
                'vulnerability_types',
                'guarantors_count',
                'guarantors_alive_status',
                'deceased_guarantors',
                'guarantors_employment_statuses',
                'affected_guarantors',
            ],
            'السكن والموقع والضرر' => [
                'displacement_status',
                'displaced_to_governorate',
                'current_residence_address',
                'loan_unit_address',
                'loan_unit_area',
                'loan_unit_floor_type',
                'parcel_number',
                'plot_number',
                'loan_unit_occupancy_status',
                'resident_households',
                'loan_unit_damage_status',
                'is_inside_yellow_line',
                'location_latitude',
                'location_longitude',
                'location_altitude',
                'location_precision',
                'boq_total_usd',
                'exchange_rate',
                'boq_total_ils',
                'full_demolition_meter_rate',
            ],
            'الخطورة والملاحظات والروابط' => [
                'risk_level',
                'risk_score',
                'risk_reasons',
                'attachments_count',
                'notes',
                'show_url',
                'pricing_url',
            ],
            'جدول الكميات BOQ' => [
                self::BOQ_HORIZONTAL_COLUMN,
            ],
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');

                $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D9E2F3'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->getStyle("A2:{$highestColumn}{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $numberColumns = $this->reportType === 'detailed'
                    ? ['F', 'G', 'H', 'J', 'K', 'L']
                    : ['E', 'F', 'G'];

                foreach ($numberColumns as $column) {
                    $sheet->getStyle("{$column}2:{$column}{$highestRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                }

                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension($highestColumn)->setWidth(45);
            },
        ];
    }

    private function borrowerCode(DamageAssessmentBorrower $borrower): string|int|null
    {
        return $borrower->form_number ?: null;
    }

    private function numberOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * @return array<int, string>
     */
    private function customColumnKeys(): array
    {
        $availableColumns = array_keys(self::availableColumns());
        $keys = collect($this->selectedColumns)
            ->filter(fn (mixed $key): bool => is_string($key) && in_array($key, $availableColumns, true))
            ->values();

        if ($keys->isEmpty()) {
            return array_keys(self::compactColumnDefaults());
        }

        return $keys
            ->reject(fn (string $key): bool => $key === self::BOQ_HORIZONTAL_COLUMN)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function compactColumnDefaults(): array
    {
        return [
            'borrower_code' => 'الكود',
            'form_number' => 'كود المستفيد',
            'borrower_name' => 'اسم المقترض',
            'borrower_id_number' => 'رقم الهوية',
            'loan_total_amount' => 'قيمة القرض',
            'loan_balance' => 'المبلغ المتبقي',
            'boq_total_usd' => 'قيمة الضرر للهدم الكلي',
            'loan_unit_damage_status' => 'نوع الضرر',
            'notes' => 'الملاحظات',
        ];
    }

    private function includesHorizontalBoq(): bool
    {
        return $this->reportType === 'custom'
            && in_array(self::BOQ_HORIZONTAL_COLUMN, $this->selectedColumns, true);
    }

    private function maxBoqItemsCount(): int
    {
        if (! $this->includesHorizontalBoq()) {
            return 0;
        }

        return (int) $this->borrowers
            ->map(fn (DamageAssessmentBorrower $borrower): int => $borrower->boqItems
                ->filter(fn ($item): bool => (float) $item->quantity > 0)
                ->count())
            ->max();
    }

    private function customColumnValue(DamageAssessmentBorrower $borrower, string $key): mixed
    {
        return match ($key) {
            'borrower_code' => $this->borrowerCode($borrower),
            'surveyed_at', 'created_at', 'updated_at', 'loan_started_at', 'loan_last_installment_at' => $borrower->{$key}?->format('Y-m-d'),
            'marital_status', 'employment_status', 'guarantors_alive_status', 'displacement_status',
            'displaced_to_governorate', 'loan_unit_floor_type', 'loan_unit_occupancy_status',
            'loan_unit_damage_status', 'risk_level' => $key === 'risk_level'
                ? $this->riskLabel($borrower->risk_level)
                : $this->optionLabel($borrower->{$key}),
            'is_borrower_alive', 'loan_clearance_delivered', 'is_inside_yellow_line' => $this->booleanLabel($borrower->{$key}),
            'vulnerability_types', 'deceased_guarantors', 'guarantors_employment_statuses',
            'affected_guarantors', 'resident_households', 'risk_reasons' => $this->stringifyList($borrower->{$key}),
            'full_demolition_meter_rate' => $this->fullDemolitionMeterRate($borrower),
            'show_url' => route('damage-assessment-borrowers.show', $borrower),
            'pricing_url' => route('damage-assessment-borrowers.pricing', $borrower),
            default => $borrower->{$key},
        };
    }

    private function booleanLabel(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        return (bool) $value ? 'نعم' : 'لا';
    }

    private function stringifyList(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): string => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE))
                ->filter()
                ->implode(' | ');
        }

        return (string) $value;
    }

    private function fullDemolitionMeterRate(DamageAssessmentBorrower $borrower): ?int
    {
        if ($borrower->loan_unit_damage_status !== 'destroyed') {
            return null;
        }

        return match ($borrower->loan_unit_floor_type) {
            'ground' => 325,
            'repeated' => 280,
            default => null,
        };
    }

    private function riskLabel(?string $riskLevel): string
    {
        return match ($riskLevel) {
            'critical' => 'حرج',
            'high' => 'مرتفع',
            'medium' => 'متوسط',
            'low' => 'منخفض',
            default => '-',
        };
    }

    private function optionLabel(?string $value): string
    {
        return match ($value) {
            'destroyed' => 'هدم كلي',
            'severe_uninhabitable' => 'متضرر بليغ غير صالح للسكن',
            'severe_habitable' => 'متضرر بليغ صالح للسكن',
            'minor' => 'أضرار طفيفة',
            'ground' => 'أرضي',
            'repeated' => 'متكرر',
            default => $value ?: '-',
        };
    }
}
