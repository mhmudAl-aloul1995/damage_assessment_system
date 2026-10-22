<?php

use App\Models\User;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehSurveyColumns;
use Database\Seeders\RehPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('stores all questionnaire answers in nullable typed columns while keeping the original snapshot', function (): void {
    $unit = RehUnit::factory()->create(['source' => ['household_size' => 5, 'household_chronic_count' => 0, 'boq_2_1_external_block_10cm_qty' => 4.5, 'OldUnit_objectID' => 5087]]);
    $raw = DB::table('reh_units')->find($unit->id);
    expect((int) $raw->household_size)->toBe(5)->and((int) $raw->household_chronic_count)->toBe(0)->and($raw->owner_age)->toBeNull()->and($raw->reference_id)->toBe('5087');
    foreach (RehSurveyColumns::FIELDS as $scope => $fields) {
        foreach ($fields as [$column]) {
            expect(Schema::hasColumn($scope === 'building' ? 'reh_buildings' : 'reh_units', $column))->toBeTrue();
        }
    }
    $decimal = collect(RehSurveyColumns::FIELDS['unit'])->filter(fn ($entry) => $entry[1] === 'decimal')->keys()->first();
    $unit->update(['source' => [...$unit->source, $decimal => 4.5]]);
    expect((float) DB::table('reh_units')->where('id', $unit->id)->value($decimal))->toBe(4.5);
    $snapshot = $unit->getRawOriginal('source');
    $unit->update(['household_size' => 9]);
    expect($unit->fresh()->surveyData()['household_size'])->toBe(9)
        ->and($unit->fresh()->source['household_size'])->toBe(5)
        ->and($unit->fresh()->getRawOriginal('source'))->toBe($snapshot);
});

it('backfills old records without changing their snapshots reviews versions or workflow and can rerun', function (): void {
    $unit = RehUnit::factory()->create(['source' => ['household_size' => 7, 'owner_age' => 38, 'OldUnit_objectID' => 42]]);
    $snapshot = $unit->getRawOriginal('source');
    DB::table('reh_units')->where('id', $unit->id)->update(['survey_columns_ready' => false, 'household_size' => null, 'owner_age' => null, 'reference_id' => null]);
    $migration = require glob(app_path('Modules/Reh/database/migrations/*add_survey_columns_to_reh_tables.php'))[0];
    $migration->up();
    $fresh = $unit->fresh();
    expect($fresh->household_size)->toBe(7)->and($fresh->owner_age)->toBe(38)->and($fresh->old_objectid)->toBe('42')
        ->and($fresh->getRawOriginal('source'))->toBe($snapshot)->and($fresh->version)->toBe(1)->and($fresh->workflow_status)->toBe('new');
    $fresh->update(['household_size' => 8]);
    $migration->up();
    expect($fresh->fresh()->household_size)->toBe(8);
});

it('backfills creator columns from both layer spellings without rewriting archived data or decisions', function (): void {
    $building = RehBuilding::factory()->create(['source' => ['Creator' => 'building-author']]);
    $unit = RehUnit::factory()->create(['building_id' => $building->id, 'source' => ['creator' => 'unit-author'], 'engineering_status' => 'approved', 'decision' => 'selected']);
    $existing = RehUnit::factory()->create(['creator' => 'preserved-author', 'source' => ['Creator' => 'archived-author']]);
    $absent = RehUnit::factory()->create(['source' => []]);
    $snapshot = $unit->getRawOriginal('source');
    $updatedAt = $unit->updated_at;
    $migration = require glob(app_path('Modules/Reh/database/migrations/*add_creator_to_reh_tables.php'))[0];
    $migration->up();
    $migration->up();
    expect($building->fresh()->creator)->toBe('building-author')
        ->and($unit->fresh()->creator)->toBe('unit-author')
        ->and($existing->fresh()->creator)->toBe('preserved-author')
        ->and($absent->fresh()->creator)->toBeNull()
        ->and($unit->fresh()->getRawOriginal('source'))->toBe($snapshot)
        ->and($unit->fresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and($unit->fresh()->engineering_status)->toBe('approved')
        ->and($unit->fresh()->decision)->toBe('selected')
        ->and($unit->fresh()->version)->toBe(1)
        ->and($unit->reviews()->count())->toBe(0);
    $migration->down();
    expect(Schema::hasColumn('reh_units', 'creator'))->toBeFalse()
        ->and(Schema::hasColumn('reh_buildings', 'creator'))->toBeFalse();
    $migration->up();
    expect($unit->fresh()->creator)->toBe('unit-author');
});

it('reads canonical columns in filters and field displays even when the archive has older values', function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo(['reh.view', 'reh.sensitive', 'reh.export', 'reh.scores.view']);
    $unit = RehUnit::factory()->create(['source' => ['household_size' => 2, 'OldUnit_objectID' => 11]]);
    $unit->update(['household_size' => 9, 'reference_id' => '77']);
    $this->actingAs($user)->getJson(route('reh.data', ['household_min' => 9, 'household_max' => 9]))->assertOk()->assertJsonPath('total_units', 1);
    $this->getJson(route('reh.data', ['household_max' => 2]))->assertOk()->assertJsonPath('total_units', 0);
    $this->getJson(route('reh.data', ['q' => '77']))->assertOk()->assertJsonPath('total_units', 1);
    $unit->building->update(['field_status' => 'Not_Completed']);
    $this->getJson(route('reh.data'))->assertOk()->assertJsonPath('total_units', 0);
});
