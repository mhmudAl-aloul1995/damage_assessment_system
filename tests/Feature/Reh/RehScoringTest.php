<?php

use App\Models\User;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehImporter;
use App\Modules\Reh\Services\RehScoring;
use App\Modules\Reh\Services\RehSurvey;
use Database\Seeders\RehPermissionsSeeder;
use Spatie\Permission\Models\Role;

function rehScoringSource(): array
{
    $source = [];
    foreach (app(RehSurvey::class)->dictionary()['fields'] as $field => $definition) {
        if (in_array($definition['type'], ['integer', 'decimal'], true)) {
            $source[$field] = 0;
        }
    }

    return [...$source, 'owner_age' => 40, 'owner_sex' => 'male', 'other_members_have_disability' => 'no', 'owner_has_disability' => 'no', 'nonfood_assistance_recency' => 'recent', 'shelter_assistance_recency' => 'recent', 'household_has_stable_income' => 'yes', 'household_protection_risks' => 'debt', 'breadwinner_male_owner' => 'owner', 'hosts_other_households' => 'no', 'current_unit_occupancy' => 'owner_family'];
}

function rehScoringUnit(array $changes = []): RehUnit
{
    return new RehUnit(['source' => [...rehScoringSource(), ...$changes], 'quantity_overrides' => []]);
}

function rehScoreRow(array $score, string $section, string $id): array
{
    return collect($score[$section]['rows'])->firstWhere('id', $id);
}

it('computes only settled indicators and never publishes a final score or ranking from source scores', function (): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['social_score' => 60, 'total_score' => 100, 'priority' => 'very_high']));
    expect($score['owner']['rows'])->toHaveCount(23)
        ->and(array_sum(array_column($score['owner']['rows'], 'maximum')))->toBe(60)
        ->and($score['engineering']['rows'])->toHaveCount(12)
        ->and($score['engineering']['total'])->toBe(0)
        ->and($score['owner']['calculated_count'])->toBe(22)
        ->and($score['owner']['subtotal'])->toBe(2)
        ->and($score['other'])->toBeNull()
        ->and($score['total'])->toBeNull()->and($score['priority'])->toBeNull()
        ->and(rehScoreRow($score, 'owner', 'dependency')['status'])->toBe('policy');
});

it('uses exact age boundaries and removes the owner from the other elder count', function (int $age, int $old, int $minor, int $others): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['owner_age' => $age, 'household_males_over60' => 1]));
    expect(rehScoreRow($score, 'owner', 'age')['points'])->toBe($old)
        ->and(rehScoreRow($score, 'owner', 'minor')['points'])->toBe($minor)
        ->and(rehScoreRow($score, 'owner', 'elders')['points'])->toBe($others);
})->with([[17, 0, 3, 2], [18, 0, 0, 2], [60, 0, 0, 2], [61, 3, 0, 0]]);

it('does not silently zero missing invalid or contradictory social answers', function (): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['owner_age' => 70, 'nonfood_assistance_recency' => '', 'shelter_assistance_recency' => 'unknown', 'household_chronic_count' => -1, 'other_vision_difficulty_count' => 1]));
    foreach (['elders', 'shelter', 'chronic', 'disability_vision'] as $id) {
        expect(rehScoreRow($score, 'owner', $id)['status'])->toBe('invalid')->and(rehScoreRow($score, 'owner', $id)['points'])->toBeNull();
    }
    expect(rehScoreRow($score, 'owner', 'nfi')['status'])->toBe('missing');
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['household_protection_risks' => '']));
    expect(rehScoreRow($score, 'owner', 'protection')['points'])->toBeNull();
});

it('uses explicit breadwinner choices without assuming the hidden child age', function (string $choice, int $expected): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['breadwinner_male_owner' => $choice, 'breadwinner_child_age' => null]));
    expect(rehScoreRow($score, 'owner', 'breadwinner')['points'])->toBe($expected);
})->with(['minor' => ['minor_child', 3], 'adult son' => ['adult_son', 2], 'adult daughter' => ['adult_daughter', 2], 'wife' => ['wife', 3], 'owner' => ['owner', 0]]);

it('deduplicates known risks and keeps hosted applicability unresolved', function (): void {
    $unit = rehScoringUnit(['hosts_other_households' => 'yes', 'hosted_individuals_count' => 4, 'nonowner_protection_risks' => 'none', 'household_protection_risks' => 'debt debt']);
    $score = app(RehScoring::class)->calculate($unit);
    expect(rehScoreRow($score, 'owner', 'protection')['points'])->toBe(2)
        ->and(rehScoreRow($score, 'other', 'protection')['points'])->toBe(0)
        ->and(rehScoreRow($score, 'other', 'head_disability')['status'])->toBe('policy')
        ->and($score['other']['rows'])->toHaveCount(23)
        ->and($score['social_total'])->toBeNull();
    $unit->source = [...$unit->source, 'nonowner_protection_risks' => 'none debt'];
    expect(rehScoreRow(app(RehScoring::class)->calculate($unit), 'other', 'protection')['status'])->toBe('invalid');
});

it('awards each engineering category once and ignores unmapped additional work', function (): void {
    $unit = rehScoringUnit();
    $source = $unit->source;
    foreach (array_keys($source) as $field) {
        if (str_starts_with($field, 'boq_')) {
            $source[$field] = 20;
        }
    }
    $unit->source = [...$source, 'additional_item_quantity_1' => 9999];
    $score = app(RehScoring::class)->calculate($unit);
    expect($score['engineering']['total'])->toBe(40)
        ->and(rehScoreRow($score, 'engineering', 'kitchen')['points'])->toBe(6);
});

it('resolves binary categories with a positive quantity but never assumes empty means zero', function (): void {
    $unit = new RehUnit(['source' => ['boq_12_1_water_tank_1000l_qty' => 1]]);
    $score = app(RehScoring::class)->calculate($unit);
    expect(rehScoreRow($score, 'engineering', 'tank')['points'])->toBe(2)
        ->and(rehScoreRow($score, 'engineering', 'kitchen')['status'])->toBe('missing')
        ->and($score['engineering']['total'])->toBeNull();
});

it('handles internal wall thresholds and defers fractional quantities', function (mixed $quantity, ?int $expected, string $status): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['boq_2_3_partition_wall_qty' => $quantity]));
    expect(rehScoreRow($score, 'engineering', 'internal_wall')['points'])->toBe($expected)
        ->and(rehScoreRow($score, 'engineering', 'internal_wall')['status'])->toBe($status);
})->with([[0, 0, 'calculated'], [1, 1, 'calculated'], [4, 1, 'calculated'], [5, 2, 'calculated'], [10, 2, 'calculated'], [11, 4, 'calculated'], [0.5, null, 'policy'], [4.5, null, 'policy'], [5.5, null, 'policy'], [null, null, 'missing'], [-1, null, 'invalid'], ['bad', null, 'invalid']]);

it('scores external wall length and area separately without adding incompatible units', function (): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['boq_2_1_pallet_wall_qty' => 4, 'boq_2_5_used_block_20cm_qty' => 4]));
    expect(rehScoreRow($score, 'engineering', 'external_wall')['points'])->toBe(1);
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['boq_2_1_pallet_wall_qty' => 5, 'boq_2_5_used_block_20cm_qty' => 11]));
    expect(rehScoreRow($score, 'engineering', 'external_wall')['points'])->toBe(4);
});

it('gives saved overrides precedence including zero and retains missing semantics', function (): void {
    $unit = rehScoringUnit(['boq_1_1_water_tank_repair_qty' => 2]);
    $unit->quantity_overrides = ['boq_1_1_water_tank_repair_qty' => 0];
    expect(rehScoreRow(app(RehScoring::class)->calculate($unit), 'engineering', 'tank')['points'])->toBe(0);
    $unit->quantity_overrides = [];
    expect(rehScoreRow(app(RehScoring::class)->calculate($unit), 'engineering', 'tank')['points'])->toBe(2);
});

it('renders real ajax scoring and records before and after snapshots on quantity review', function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $officer = User::factory()->create();
    $officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $unit = RehUnit::factory()->create(['source' => rehScoringSource()]);
    $response = $this->actingAs($officer)->getJson(route('reh.units.show', $unit))->assertOk();
    expect($response->json('html'))->toContain('data-scoring', 'الأولوية غير مصنفة', 'نسبة الإعالة');
    $response = $this->postJson(route('reh.units.review', $unit), ['action' => 'quantities', 'version' => 1, 'note' => 'تدقيق الحائط الداخلي', 'quantities' => ['boq_2_3_partition_wall_qty' => 11]])->assertOk();
    expect($response->json('html'))->toContain('data-engineering-subtotal="4"', 'قبل 0', 'بعد 4');
    $changes = $unit->reviews()->latest()->first()->changes['scoring'];
    expect($changes['before']['engineering']['subtotal'])->toBe(0)
        ->and($changes['after']['engineering']['subtotal'])->toBe(4)
        ->and($changes['after']['total'])->toBeNull()
        ->and($changes['after']['version'])->toBe(RehScoring::VERSION);
    $this->postJson(route('reh.units.review', $unit), ['action' => 'quantities', 'version' => 1, 'note' => 'نسخة قديمة', 'quantities' => ['boq_2_3_partition_wall_qty' => 0]])->assertConflict();
    expect($unit->reviews()->count())->toBe(1);
});

it('hides social scoring and social audit snapshots from readers without sensitive access', function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo(['reh.view', 'reh.export']);
    $unit = RehUnit::factory()->create(['source' => rehScoringSource()]);
    $snapshot = app(RehScoring::class)->snapshot($unit);
    $unit->reviews()->create(['action' => 'quantities', 'note' => 'مراجعة', 'changes' => ['scoring' => ['before' => $snapshot, 'after' => $snapshot]]]);
    $html = $this->actingAs($user)->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    expect($html)->toContain('data-score-panel="engineering"')->not->toContain('data-score-panel="social"', 'إعاقة المالك', 'مخاطر الحماية', 'أسرة المالك · نقاط محسومة');
    $csv = $this->get(route('reh.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('نقاط هندسية محسومة', 'غير مصنفة', RehScoring::VERSION)->not->toContain('نقاط اجتماعية');
});

it('keeps an immutable scoring snapshot when source data changes and ignores repeated imports', function (): void {
    $unit = RehUnit::factory()->create(['source' => rehScoringSource()]);
    $source = [...$unit->source, 'globalid' => $unit->global_id, 'objectid' => $unit->object_id, 'parentglobalid' => $unit->building->global_id, 'boq_2_3_partition_wall_qty' => 11];
    $importer = app(RehImporter::class);
    $importer->unit(['attributes' => $source]);
    $review = $unit->reviews()->first();
    expect($review->changes['scoring']['before']['engineering']['subtotal'])->toBe(0)
        ->and($review->changes['scoring']['after']['engineering']['subtotal'])->toBe(4);
    $importer->unit(['attributes' => $source]);
    expect($unit->reviews()->count())->toBe(1);
});
