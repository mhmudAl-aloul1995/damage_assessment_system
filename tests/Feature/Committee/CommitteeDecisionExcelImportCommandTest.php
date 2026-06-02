<?php

use App\Models\Building;
use App\Models\CommitteeDecision;
use App\Models\CommitteeDecisionSignature;
use App\Models\CommitteeMember;
use App\Models\HousingUnit;
use App\Models\User;
use App\services\CommitteeDecisionExcelImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('imports temporary committee decision excel rows into decisions members and signature slots', function () {
    $building = Building::query()->create([
        'objectid' => 1001,
        'globalid' => 'building-globalid-1001',
        'building_name' => 'Imported Building',
        'building_damage_status' => 'committee_review',
    ]);

    $unit = HousingUnit::query()->create([
        'objectid' => 2001,
        'globalid' => 'unit-globalid-2001',
        'parentglobalid' => $building->globalid,
        'housing_unit_number' => 'U-1',
        'unit_damage_status' => 'committee_review2',
    ]);

    $memberOneUser = User::factory()->create(['name' => 'محسن حرزالله']);
    $memberTwoUser = User::factory()->create(['name' => 'رامي شقورة']);
    User::factory()->create(['name' => 'عدنان العكلوك']);

    $path = committeeDecisionWorkbookPath([
        [
            'objectid' => 1001,
            'globalid' => 'building-globalid-1001',
            'decision' => 'هدم كلي',
            'action' => 'اعادة المبنى للمهندس لحصره',
            'members' => 'م. محسن حرزالله-مجلس الاسكان/رامي شقورة -الاسكان',
            'hint' => '',
        ],
        [
            'objectid' => 2001,
            'globalid' => 'unit-globalid-2001',
            'decision' => 'ضرر جزئي',
            'action' => 'اعادة المبنى للمهندس لحصره',
            'members' => 'عدنان العكلوك -UNDP/عضو غير موجود -وزارة الاشغال',
            'hint' => 'وحدات',
        ],
    ]);

    $this->artisan('committee-decisions:import-excel', ['file' => $path])
        ->assertSuccessful();

    $buildingDecision = CommitteeDecision::query()
        ->whereMorphedTo('decisionable', $building)
        ->firstOrFail();
    $unitDecision = CommitteeDecision::query()
        ->whereMorphedTo('decisionable', $unit)
        ->firstOrFail();

    expect($buildingDecision->decision_type)->toBe('fully_damaged')
        ->and($buildingDecision->decision_text)->toBe('هدم كلي')
        ->and($buildingDecision->action_text)->toBe('اعادة المبنى للمهندس لحصره')
        ->and($unitDecision->decision_type)->toBe('partially_damaged')
        ->and($unitDecision->notes)->toContain('عضو غير موجود');

    expect(CommitteeMember::query()->where('user_id', $memberOneUser->id)->exists())->toBeTrue()
        ->and(CommitteeMember::query()->where('user_id', $memberTwoUser->id)->exists())->toBeTrue()
        ->and(CommitteeDecisionSignature::query()->where('committee_decision_id', $buildingDecision->id)->count())->toBe(2)
        ->and(CommitteeDecisionSignature::query()->where('committee_decision_id', $unitDecision->id)->count())->toBe(1);
});

it('can dry run the temporary excel import without saving records', function () {
    Building::query()->create([
        'objectid' => 1001,
        'globalid' => 'building-globalid-1001',
        'building_name' => 'Imported Building',
        'building_damage_status' => 'committee_review',
    ]);
    User::factory()->create(['name' => 'محسن حرزالله']);

    $path = committeeDecisionWorkbookPath([
        [
            'objectid' => 1001,
            'globalid' => 'building-globalid-1001',
            'decision' => 'هدم كلي',
            'action' => 'اعادة المبنى للمهندس لحصره',
            'members' => 'م. محسن حرزالله-مجلس الاسكان',
            'hint' => '',
        ],
    ]);

    $this->artisan('committee-decisions:import-excel', ['file' => $path, '--dry-run' => true])
        ->assertSuccessful();

    expect(CommitteeDecision::query()->count())->toBe(0)
        ->and(CommitteeMember::query()->count())->toBe(0)
        ->and(CommitteeDecisionSignature::query()->count())->toBe(0);
});

it('skips committee decision rows that are not fully or partially damaged', function () {
    Building::query()->create([
        'objectid' => 3001,
        'globalid' => 'building-globalid-3001',
        'building_name' => 'Skipped Building',
        'building_damage_status' => 'committee_review',
    ]);

    $summary = app(CommitteeDecisionExcelImportService::class)->importRecords([
        [
            'objectid' => 3001,
            'globalid' => 'building-globalid-3001',
            'decision' => 'تحول لجنة فنية أخرى',
            'action' => null,
            'members' => null,
            'hint' => null,
        ],
    ]);

    expect($summary['rows'])->toBe(1)
        ->and($summary['skipped_rows'])->toBe(1)
        ->and($summary['issues'][0]['reason'])->toBe('Decision text is not classified as fully or partially damaged.')
        ->and(CommitteeDecision::query()->count())->toBe(0);
});

function committeeDecisionWorkbookPath(array $records): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $headers = [
        'ObjectID',
        'GlobalID',
        'Field_status',
        'رقم القطعة',
        'اسم الباحث',
        'رقم المجموعة',
        'end',
        'What is the current damage status of the building?',
        '1.1 Building Type',
        '1.2 Building Use',
        'Building Name',
        '6.1 Comments & Recommendations',
        'Shape__Area',
        'Shape__Length',
        'CreationDate',
        'EditDate',
        'Editor',
        'security information',
        'المحافظة',
        'البلدية',
        'الحي',
        '',
        '',
        'أعضاء اللجنة الفنية',
        '',
    ];

    foreach ($headers as $index => $header) {
        $sheet->setCellValue([$index + 1, 1], $header);
    }

    foreach ($records as $rowIndex => $record) {
        $row = $rowIndex + 2;
        $sheet->setCellValue("A{$row}", $record['objectid']);
        $sheet->setCellValue("B{$row}", $record['globalid']);
        $sheet->setCellValue("V{$row}", $record['decision']);
        $sheet->setCellValue("W{$row}", $record['action']);
        $sheet->setCellValue("X{$row}", $record['members']);
        $sheet->setCellValue("Y{$row}", $record['hint']);
    }

    $path = tempnam(sys_get_temp_dir(), 'committee-decisions-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}
