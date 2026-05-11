<?php

namespace App\Exports;

use App\Models\AssessmentStatus;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AuditBuildingsExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $buildingColumns,
        private readonly Builder $buildingQuery,
        private readonly bool $includeHousingUnits,
        private readonly array $housingColumns = [],
        private readonly ?Builder $housingQuery = null,
    ) {}

    public function sheets(): array
    {
        $sheets = [
            new AuditBuildingsSheet($this->buildingColumns, $this->buildingQuery),
        ];

        if ($this->includeHousingUnits && $this->housingQuery !== null) {
            $sheets[] = new AuditHousingUnitsSheet($this->housingColumns, $this->housingQuery);
        }

        return $sheets;
    }
}

abstract class AuditSheet implements FromQuery, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly array $columns,
        private readonly Builder $query,
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return array_values($this->columns);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '0B1B46'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3F6F9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return collect(range('A', $this->excelColumnName(count($this->columns))))
            ->mapWithKeys(fn (string $column): array => [$column => 24])
            ->all();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');

                if ($highestColumn !== 'A' || $highestRow > 1) {
                    $sheet->setAutoFilter('A1:'.$highestColumn.'1');
                }

                $sheet->getStyle('A1:'.$highestColumn.'1')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return $this->columns;
    }

    protected function latestBuildingStatusRecord(Building $building): ?BuildingStatus
    {
        return $building->buildingStatuses
            ->sortByDesc(fn (BuildingStatus $status): string => $this->statusSortKey($status->updated_at, $status->id))
            ->first();
    }

    protected function latestHousingStatusRecord(HousingUnit $unit): ?HousingStatus
    {
        return $unit->housingStatuses
            ->sortByDesc(fn (HousingStatus $status): string => $this->statusSortKey($status->updated_at, $status->id))
            ->first();
    }

    protected function auditStatusLabel(?AssessmentStatus $status): string
    {
        if ($status === null) {
            return 'Pending';
        }

        return (string) ($status->label_ar ?: $status->label_en ?: $status->name);
    }

    protected function formatAuditExportDate(mixed $value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function statusSortKey(mixed $date, mixed $id): string
    {
        $timestamp = blank($date) ? '00000000000000' : Carbon::parse($date)->format('YmdHis');

        return $timestamp.str_pad((string) $id, 12, '0', STR_PAD_LEFT);
    }

    private function excelColumnName(int $columnNumber): string
    {
        $name = '';

        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $columnNumber = intdiv($columnNumber - 1, 26);
        }

        return $name;
    }
}

class AuditBuildingsSheet extends AuditSheet
{
    public function __construct(array $columns, Builder $query)
    {
        parent::__construct('Buildings', $columns, $query);
    }

    public function map($row): array
    {
        /** @var Building $building */
        $building = $row;
        $engineer = $building->assignedUsers->firstWhere('type', 'QC/QA Engineer')?->user?->name;
        $lawyer = $building->assignedUsers->firstWhere('type', 'Legal Auditor')?->user?->name;
        $latestBuildingStatus = $this->latestBuildingStatusRecord($building);
        $housingUnitsCount = (int) ($building->housing_units_count ?? 0);
        $housingUnitsWithStatusCount = (int) ($building->housing_units_with_status_count ?? 0);

        $values = [
            'objectid' => $building->objectid,
            'globalid' => $building->globalid,
            'building_name' => $building->building_name,
            'governorate' => $building->governorate,
            'municipality' => $building->municipalitie,
            'neighborhood' => $building->neighborhood,
            'assignedto' => $building->assignedto,
            'building_damage_status' => $building->building_damage_status,
            'creationdate' => $this->formatAuditExportDate($building->creationdate),
            'engineer' => $engineer,
            'lawyer' => $lawyer,
            'engineer_status' => $this->auditStatusLabel($building->engineerStatus?->status),
            'lawyer_status' => $this->auditStatusLabel($building->lawyerStatus?->status),
            'final_status' => $this->auditStatusLabel($building->finalApproval?->status),
            'housing_status_progress' => $housingUnitsWithStatusCount.' / '.$housingUnitsCount,
            'housing_units_count' => $housingUnitsCount,
            'housing_units_with_status_count' => $housingUnitsWithStatusCount,
            'building_status_notes' => $latestBuildingStatus?->notes,
        ];

        return collect(array_keys($this->columns()))
            ->map(fn (string $column): mixed => $values[$column] ?? '')
            ->all();
    }
}

class AuditHousingUnitsSheet extends AuditSheet
{
    public function __construct(array $columns, Builder $query)
    {
        parent::__construct('Housing Units', $columns, $query);
    }

    public function map($row): array
    {
        /** @var HousingUnit $unit */
        $unit = $row;
        $building = $unit->building;
        $latestHousingStatus = $this->latestHousingStatusRecord($unit);

        $values = [
            'building_objectid' => $building?->objectid,
            'building_name' => $building?->building_name,
            'governorate' => $unit->governorate,
            'municipality' => $unit->municipalitie,
            'neighborhood' => $unit->neighborhood,
            'objectid' => $unit->objectid,
            'globalid' => $unit->globalid,
            'parentglobalid' => $unit->parentglobalid,
            'housing_unit_number' => $unit->housing_unit_number,
            'floor_number' => $unit->floor_number,
            'housing_unit_type' => $unit->housing_unit_type,
            'unit_damage_status' => $unit->unit_damage_status,
            'unit_owner' => $unit->unit_owner,
            'engineer_status' => $this->auditStatusLabel($unit->engineerStatus?->assessment_status),
            'lawyer_status' => $this->auditStatusLabel($unit->lawyerStatus?->assessment_status),
            'final_status' => $this->auditStatusLabel($unit->finalApproval?->assessment_status),
            'housing_status_notes' => $latestHousingStatus?->notes,
        ];

        return collect(array_keys($this->columns()))
            ->map(fn (string $column): mixed => $values[$column] ?? '')
            ->all();
    }
}
