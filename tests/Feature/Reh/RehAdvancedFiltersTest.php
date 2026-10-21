<?php

use App\Models\User;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehScoring;
use App\Modules\Reh\Services\RehSurvey;
use Database\Seeders\RehPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['reh.view', 'reh.export', 'reh.scores.view', 'reh.sensitive']);
    $this->actingAs($this->user);
});

function rehFilterCompleteSource(): array
{
    $source = [];
    foreach (app(RehSurvey::class)->dictionary()['fields'] as $field => $definition) {
        if (in_array($definition['type'], ['integer', 'decimal'], true)) {
            $source[$field] = 0;
        }
    }

    return [...$source, 'owner_age' => 40, 'household_size' => 1, 'household_males_age18_60' => 1, 'owner_sex' => 'male', 'other_members_have_disability' => 'no', 'owner_has_disability' => 'no', 'nonfood_assistance_recency' => 'recent', 'shelter_assistance_recency' => 'recent', 'household_has_stable_income' => 'yes', 'household_protection_risks' => 'debt', 'breadwinner_male_owner' => 'owner', 'hosts_other_households' => 'no', 'current_unit_occupancy' => 'owner_family', 'unit_needs_intervention' => 'no'];
}

it('applies intersecting visit family and location filters consistently to rows map counts and csv', function (): void {
    $building = RehBuilding::factory()->create(['source' => ['Field_status' => 'COMPLETED', 'visit_date' => \Carbon\CarbonImmutable::parse('2026-10-20', config('app.timezone'))->getTimestampMs(), 'Old_objectID' => 2328]]);
    $unit = RehUnit::factory()->create(['building_id' => $building->id, 'code' => 'MATCH-FILTER', 'source' => ['household_size' => '5', 'OldUnit_objectID' => 5087, 'owner_has_disability' => 'yes']]);
    RehUnit::factory()->create(['building_id' => $building->id, 'code' => 'NOT-MATCHING', 'source' => ['household_size' => 7, 'owner_has_disability' => 'yes']]);
    RehUnit::factory()->create(['code' => 'MISSING-DATE', 'source' => ['household_size' => 5, 'owner_has_disability' => 'yes']]);
    $filters = ['visit_from' => '2026-10-20', 'visit_to' => '2026-10-20', 'household_min' => 5, 'household_max' => 5, 'neighborhood' => $building->neighborhood, 'owner_disability' => 'yes'];
    $response = $this->getJson(route('reh.data', [...$filters, 'tab' => 'units']))->assertOk()->assertJsonPath('total_units', 1)->assertJsonPath('total_buildings', 1);
    expect($response->json('html'))->toContain('MATCH-FILTER')->not->toContain('NOT-MATCHING', 'MISSING-DATE');
    $map = $this->getJson(route('reh.data', [...$filters, 'tab' => 'buildings']))->assertOk()->assertJsonPath('total_buildings', 1);
    expect($map->json('html'))->toContain('5087')->not->toContain('NOT-MATCHING');
    $csv = $this->get(route('reh.export', $filters))->assertOk()->streamedContent();
    expect($csv)->toContain('MATCH-FILTER')->not->toContain('NOT-MATCHING', 'MISSING-DATE');
});

it('excludes missing family values from zero and no while accepting explicit zero', function (): void {
    RehUnit::factory()->create(['code' => 'EXPLICIT-ZERO', 'source' => ['household_size' => 0, 'household_chronic_count' => 0]]);
    RehUnit::factory()->create(['code' => 'UNKNOWN-VALUE', 'source' => ['household_size' => null, 'household_chronic_count' => null]]);
    $html = $this->getJson(route('reh.data', ['household_min' => 0, 'household_max' => 0, 'family_chronic' => 'no']))->assertOk()->json('html');
    expect($html)->toContain('EXPLICIT-ZERO')->not->toContain('UNKNOWN-VALUE');
});

it('uses complete scores and inclusive boundaries for score ranges without treating missing as zero', function (): void {
    $unit = RehUnit::factory()->create(['source' => rehFilterCompleteSource(), 'code' => 'SCORED']);
    RehUnit::factory()->create(['source' => [], 'code' => 'UNSCORED']);
    $score = app(RehScoring::class)->calculate($unit);
    expect($score['total'])->not->toBeNull();
    $html = $this->getJson(route('reh.data', ['score_min' => $score['total'], 'score_max' => $score['total'], 'engineering_min' => 0, 'engineering_max' => 0, 'social_min' => $score['social_total'], 'social_max' => $score['social_total'], 'evaluation' => 'complete']))->assertOk()->json('html');
    expect($html)->toContain('SCORED')->not->toContain('UNSCORED');
    $this->getJson(route('reh.data', ['evaluation' => 'incomplete']))->assertOk()->assertJsonPath('total_units', 1);
    foreach ([['score_op' => 'is', 'score' => $score['total']], ['score_op' => 'gte', 'score' => 0], ['score_op' => 'blank'], ['score_op' => 'not_blank'], ['priority_op' => 'is_not', 'priority' => 'unclassified'], ['evaluation_op' => 'is_not', 'evaluation' => 'complete']] as $filters) {
        $this->getJson(route('reh.data', $filters))->assertOk()->assertJsonPath('total_units', 1);
    }
});

it('matches successful visible edits on a unit or its building and ignores failed edits', function (): void {
    $unit = RehUnit::factory()->create();
    $other = RehUnit::factory()->create();
    $base = ['record_kind' => 'building', 'record_id' => $unit->building_id, 'object_id' => $unit->building->object_id, 'global_id' => $unit->building->global_id, 'layer_id' => 0, 'field' => 'building_name', 'values' => ['before' => 'a', 'after' => 'b'], 'reason' => 'تصحيح الاسم', 'status' => 'completed'];
    RehAuditLog::query()->create($base);
    RehAuditLog::query()->create([...$base, 'record_kind' => 'unit', 'record_id' => $other->id, 'status' => 'failed']);
    $this->getJson(route('reh.data', ['edits' => 'yes']))->assertOk()->assertJsonPath('total_units', 1);
    $this->getJson(route('reh.data', ['edits' => 'no']))->assertOk()->assertJsonPath('total_units', 1);
    $this->getJson(route('reh.data', ['edits_op' => 'is_not', 'edits' => 'yes']))->assertOk()->assertJsonPath('total_units', 1);
    $this->getJson(route('reh.data', ['edits_op' => 'is_not', 'edits' => ['yes', 'no']]))->assertOk()->assertJsonPath('total_units', 0);
});

it('validates advanced ranges and denies restricted filtering on every endpoint', function (): void {
    $this->getJson(route('reh.data', ['score_min' => 50, 'score_max' => 10, 'visit_from' => '2026-10-20', 'visit_to' => '2026-10-01']))->assertUnprocessable()->assertJsonValidationErrors(['score_max', 'visit_to']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($viewer);
    foreach (['reh.index', 'reh.data', 'reh.export', 'reh.export.pdf'] as $route) {
        foreach (['household_min' => 0, 'owner_disability' => 'no', 'score_min' => 0, 'social_min' => 0, 'evaluation' => 'incomplete'] as $filter => $value) {
            $this->getJson(route($route, [$filter => $value]))->assertForbidden();
        }
    }
    $this->get(route('reh.index'))->assertOk()->assertDontSee('id="reh-owner_disability"', false)->assertDontSee('id="reh-score-min"', false)->assertSee('فلاتر متقدمة');
});
