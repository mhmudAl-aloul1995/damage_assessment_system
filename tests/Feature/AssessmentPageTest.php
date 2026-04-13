<?php

use App\Models\Assessment;
use App\Models\Building;
use App\Models\EditAssessment;
use App\Models\HousingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('shows the building name and pdf export action on the assessment page', function () {
    $user = User::factory()->create();

    $building = Building::query()->create([
        'objectid' => 101,
        'globalid' => 'building-101',
        'building_name' => 'Tower A',
    ]);

    HousingUnit::query()->create([
        'objectid' => 201,
        'globalid' => 'housing-201',
        'parentglobalid' => $building->globalid,
        'q_9_3_1_first_name' => 'Ali',
        'q_9_3_4_last_name' => 'Saleh',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('assessment.show', $building->globalid));

    $response->assertOk();
    $response->assertSee('Tower A');
    $response->assertSee(route('assessment.pdf', $building->globalid), false);
    $response->assertSee('PDF');
});

it('exports the assessment page as a pdf', function () {
    Pdf::fake();

    $user = User::factory()->create();

    Assessment::query()->create([
        'name' => 'owner_name',
        'label' => 'Owner Name',
        'hint' => 'Owner full name',
    ]);

    Assessment::query()->create([
        'name' => 'q_9_3_1_first_name',
        'label' => 'First Name',
        'hint' => 'Housing owner first name',
    ]);

    $building = Building::query()->create([
        'objectid' => 101,
        'globalid' => 'building-101',
        'building_name' => 'Tower A',
        'owner_name' => 'Original Owner',
    ]);

    HousingUnit::query()->create([
        'objectid' => 201,
        'globalid' => 'housing-201',
        'parentglobalid' => $building->globalid,
        'q_9_3_1_first_name' => 'Ali',
        'q_9_3_4_last_name' => 'Saleh',
    ]);

    EditAssessment::query()->create([
        'global_id' => $building->globalid,
        'type' => 'building_table',
        'field_name' => 'owner_name',
        'field_value' => 'Edited Owner',
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('assessment.pdf', $building->globalid));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        return $pdf->viewName === 'pdf.assessment'
            && $pdf->isDownload()
            && $pdf->contains('Tower A')
            && $pdf->contains('Edited Owner')
            && $pdf->contains('Ali');
    });
});
