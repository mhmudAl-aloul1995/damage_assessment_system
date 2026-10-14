<?php

use App\Models\Assessment;
use App\Models\AssessmentEditHistory;
use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->engineer = User::factory()->create(['name' => 'مهندس التدقيق']);
    $this->engineer->assignRole(Role::findOrCreate('QC/QA Engineer', 'web'));
    $this->otherEngineer = User::factory()->create();
    $this->otherEngineer->assignRole(Role::findOrCreate('Engineering Auditor', 'web'));
    $this->viewer = User::factory()->create();

    $this->building = Building::query()->create([
        'objectid' => 9401,
        'globalid' => 'change-log-date-building',
        'building_name' => 'مبنى الاختبار',
    ]);
    $this->unit = HousingUnit::query()->create([
        'objectid' => 9402,
        'globalid' => 'change-log-date-unit',
        'parentglobalid' => $this->building->globalid,
        'housing_unit_number' => '001',
    ]);
    Assessment::query()->create(['name' => 'building_name', 'label' => 'اسم المبنى']);

    $this->createChange = function (string $date, array $attributes = []): AssessmentEditHistory {
        $history = new AssessmentEditHistory(array_merge([
            'global_id' => $this->building->globalid,
            'objectid' => $this->building->objectid,
            'type' => 'building_table',
            'field_name' => 'building_name',
            'old_value' => 'قديم',
            'new_value' => 'جديد',
            'edited_by' => $this->engineer->id,
        ], $attributes));
        $history->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

        return $history;
    };
});

it('filters both record types by the inclusive edit date range', function (): void {
    ($this->createChange)('2026-10-09 23:59:59');
    $start = ($this->createChange)('2026-10-10 00:00:00');
    $end = ($this->createChange)('2026-10-12 23:59:59', [
        'global_id' => $this->unit->globalid,
        'objectid' => $this->unit->objectid,
        'type' => 'housing_table',
        'edited_by' => $this->otherEngineer->id,
    ]);
    ($this->createChange)('2026-10-13 00:00:00');
    ($this->createChange)('2026-10-11 10:00:00', ['edited_by' => $this->viewer->id]);

    $this->actingAs($this->viewer)->getJson(route('audit.engineer-change-log', [
        'from_date' => '2026-10-10', 'to_date' => '2026-10-12',
    ]))->assertOk()
        ->assertJsonPath('recordsTotal', 4)
        ->assertJsonPath('recordsFiltered', 2)
        ->assertJsonPath('data.0.id', $end->id)
        ->assertJsonPath('data.0.housing_unit_number', '001')
        ->assertJsonPath('data.1.id', $start->id);

    $this->getJson(route('audit.engineer-change-log', [
        'from_date' => '2026-10-12', 'to_date' => '2026-10-12',
    ]))->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.id', $end->id);

    $this->getJson(route('audit.engineer-change-log'))->assertOk()->assertJsonPath('recordsFiltered', 4);
});

it('rejects invalid edit date ranges for listing and export', function (array $filters, string $error): void {
    $this->actingAs($this->viewer);

    foreach (['audit.engineer-change-log', 'audit.engineer-change-log.export'] as $route) {
        $this->getJson(route($route, $filters))->assertUnprocessable()->assertJsonValidationErrors($error);
    }
})->with([
    'invalid date' => [['from_date' => '2026-02-30', 'to_date' => '2026-03-01'], 'from_date'],
    'reversed range' => [['from_date' => '2026-10-12', 'to_date' => '2026-10-10'], 'to_date'],
    'missing end' => [['from_date' => '2026-10-10'], 'to_date'],
    'missing start' => [['to_date' => '2026-10-10'], 'from_date'],
]);

it('exports all matching changes across chunks with the same filters and literal values', function (string $recordType): void {
    $isUnit = $recordType === 'housing_table';
    $attributes = [
        'global_id' => $isUnit ? $this->unit->globalid : $this->building->globalid,
        'objectid' => $isUnit ? $this->unit->objectid : $this->building->objectid,
        'type' => $recordType,
        'field_name' => 'building_name',
        'old_value' => '00123',
        'new_value' => '=1+1',
        'edited_by' => $this->engineer->id,
    ];
    AssessmentEditHistory::query()->insert(array_fill(0, 501, array_merge($attributes, [
        'created_at' => '2026-10-10 23:59:59', 'updated_at' => '2026-10-10 23:59:59',
    ])));
    foreach ([
        ['edited_by' => $this->otherEngineer->id],
        ['edited_by' => $this->viewer->id],
        ['field_name' => 'different_field'],
        ['type' => $isUnit ? 'building_table' : 'housing_table'],
        ['old_value' => 'not matching'],
    ] as $excluded) {
        ($this->createChange)('2026-10-10 12:00:00', array_merge($attributes, $excluded));
    }
    ($this->createChange)('2026-10-11 00:00:00', $attributes);

    $filters = [
        'engineer_id' => $this->engineer->id,
        'field_name' => 'building_name',
        'record_type' => $recordType,
        'search_value' => '00123',
        'from_date' => '2026-10-10',
        'to_date' => '2026-10-10',
        'start' => 10,
        'length' => 10,
    ];
    $this->actingAs($this->viewer)->getJson(route('audit.engineer-change-log', $filters))
        ->assertOk()->assertJsonPath('recordsFiltered', 501)->assertJsonCount(10, 'data');

    $response = $this->get(route('audit.engineer-change-log.export', $filters))
        ->assertOk()->assertDownload();
    $spreadsheet = IOFactory::load($response->baseResponse->getFile()->getPathname());

    try {
        $sheet = $spreadsheet->getActiveSheet();
        expect($sheet->getHighestRow())->toBe(502)
            ->and($sheet->getRightToLeft())->toBeTrue()
            ->and($sheet->getCell('A2')->getValue())->toBe($isUnit ? 'وحدة' : 'مبنى')
            ->and($sheet->getCell('C2')->getValue())->toBe('مبنى الاختبار')
            ->and($sheet->getCell('G2')->getValue())->toBe('مهندس التدقيق')
            ->and($sheet->getCell('H2')->getValue())->toBe('اسم المبنى')
            ->and($sheet->getCell('J2')->getValue())->toBe('00123')
            ->and($sheet->getCell('K2')->getValue())->toBe('=1+1')
            ->and($sheet->getCell('K2')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('L502')->getValue())->toBe('2026-10-10 23:59');

        if ($isUnit) {
            expect($sheet->getCell('E502')->getValue())->toBe('001');
        }
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
})->with(['building_table', 'housing_table']);

it('exports headers when no changes match', function (): void {
    $response = $this->actingAs($this->viewer)->get(route('audit.engineer-change-log.export'))
        ->assertOk()->assertDownload();
    $spreadsheet = IOFactory::load($response->baseResponse->getFile()->getPathname());

    try {
        expect($spreadsheet->getActiveSheet()->getHighestRow())->toBe(1)
            ->and($spreadsheet->getActiveSheet()->getCell('L1')->getValue())->toBe('تاريخ التعديل');
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
});

it('requires authentication for the change log and its export', function (): void {
    $this->getJson(route('audit.engineer-change-log'))->assertUnauthorized();
    $this->getJson(route('audit.engineer-change-log.export'))->assertUnauthorized();
});
