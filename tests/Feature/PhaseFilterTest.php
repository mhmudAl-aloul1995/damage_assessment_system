<?php

use App\Jobs\ExportDataJob;
use App\Models\Building;
use App\Models\Export;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\User;
use App\Services\ArcgisService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Cache::flush();
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
});

it('filters dashboard building and housing statistics by the selected phase', function (): void {
    $user = User::factory()->create();

    $this->app->instance(ArcgisService::class, new class extends ArcgisService
    {
        public function getToken(): string
        {
            return 'fake-token';
        }
    });

    Building::query()->create([
        'objectid' => 101,
        'globalid' => 'phase-one-building',
        'building_name' => 'Phase One Building',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'fully_damaged',
        'submission_date' => '2026-08-01 10:00:00',
        'phase_number' => 1,
    ]);

    Building::query()->create([
        'objectid' => 202,
        'globalid' => 'phase-two-building',
        'building_name' => 'Phase Two Building',
        'field_status' => 'COMPLETED',
        'building_damage_status' => 'partially_damaged',
        'submission_date' => '2026-08-02 10:00:00',
        'phase_number' => 2,
    ]);

    HousingUnit::query()->create([
        'objectid' => 301,
        'globalid' => 'phase-one-unit',
        'parentglobalid' => 'phase-one-building',
        'unit_damage_status' => 'fully_damaged2',
        'building_submit_date' => '2026-08-01 10:00:00',
    ]);

    HousingUnit::query()->create([
        'objectid' => 302,
        'globalid' => 'phase-two-unit',
        'parentglobalid' => 'phase-two-building',
        'unit_damage_status' => 'partially_damaged2',
        'building_submit_date' => '2026-08-02 10:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('damageAssessment.index', ['phase' => 2]))
        ->assertOk()
        ->assertSee('PH2')
        ->assertViewHas('buildingStats', function (array $stats): bool {
            return (int) $stats['completed'] === 1
                && (int) $stats['fully_damaged'] === 0
                && (int) $stats['partially_damaged'] === 1;
        })
        ->assertViewHas('unitStats', function (array $stats): bool {
            return (int) $stats['total_units'] === 1
                && (int) $stats['fully_damaged'] === 0
                && (int) $stats['partially_damaged'] === 1;
        });
});

it('filters public building reports by the selected phase', function (): void {
    $viewerRole = Role::query()->create([
        'name' => 'Database Officer',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    PublicBuildingSurvey::query()->create([
        'objectid' => 401,
        'globalid' => 'phase-one-public-building',
        'building_name' => 'Phase One School',
        'building_damage_status' => 'fully_damaged',
        'date_of_damage' => '2026-08-01',
        'phase_number' => 1,
    ]);

    PublicBuildingSurvey::query()->create([
        'objectid' => 402,
        'globalid' => 'phase-two-public-building',
        'building_name' => 'Phase Two Clinic',
        'building_damage_status' => 'partially_damaged',
        'date_of_damage' => '2026-08-02',
        'phase_number' => 2,
    ]);

    $this->actingAs($viewer)
        ->get(route('reports.public-buildings', [
            'phase' => 2,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]))
        ->assertOk()
        ->assertSee('PH2')
        ->assertViewHas('summaryCards', function (array $summaryCards): bool {
            return collect($summaryCards)
                ->contains(fn (array $card): bool => $card['label'] === 'Total Surveys' && $card['value'] === 1);
        });
});

it('stores the selected phase with queued data exports', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('export.data.download', ['phase' => 2]), [
            'export_mode' => 'data',
            'export_type' => 'excel',
            'building_columns' => ['objectid'],
        ])
        ->assertOk()
        ->assertJson([
            'status' => true,
        ]);

    Queue::assertPushed(ExportDataJob::class);

    $exportPayload = json_decode((string) Export::query()->firstOrFail()->filters, true);

    expect($exportPayload['selected_phase_number'] ?? null)->toBe(2);
});
