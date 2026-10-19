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

    return [...$source, 'owner_age' => 40, 'household_males_age18_60' => 1, 'owner_sex' => 'male', 'other_members_have_disability' => 'no', 'owner_has_disability' => 'no', 'nonfood_assistance_recency' => 'recent', 'shelter_assistance_recency' => 'recent', 'household_has_stable_income' => 'yes', 'household_protection_risks' => 'debt', 'breadwinner_male_owner' => 'owner', 'hosts_other_households' => 'no', 'current_unit_occupancy' => 'owner_family', 'unit_needs_intervention' => 'no'];
}

function rehScoringUnit(array $changes = []): RehUnit
{
    return new RehUnit(['source' => [...rehScoringSource(), ...$changes], 'quantity_overrides' => []]);
}

function rehScoreRow(array $score, string $section, string $id): array
{
    return collect($score[$section]['rows'])->firstWhere('id', $id);
}

function rehScoringDisplacedSource(): array
{
    return [
        'current_unit_occupancy' => 'displaced_family', 'hosts_other_households' => 'no',
        'resident_age' => 40, 'resident_sex' => 'male', 'displaced_families_count' => 2, 'displaced_individuals_count' => 15,
        'nonowner_has_disability' => 'no', 'nonowner_nfi_recency' => 'm6_12', 'nonowner_shelter_recency' => 'm12_plus',
        'nonowner_has_stable_income' => 'no', 'nonowner_protection_risks' => 'none', 'nonowner_breadwinner_m' => 'adult_son',
        'nonowner_males_age18_60' => 2, 'nonowner_females_age18_60' => 2,
        'nonowner_males_under5' => 4, 'nonowner_females_under5' => 3,
        'nonowner_males_age5_17' => 2, 'nonowner_females_age5_17' => 2,
        'nonowner_chronic_count' => 2, 'nonowner_pregnant_lactating' => 1,
    ];
}

function rehScoringBlankBoq(): array
{
    return array_fill_keys(array_filter(array_keys(app(RehSurvey::class)->dictionary()['fields']), fn (string $field): bool => str_starts_with($field, 'boq_')), null);
}

it('renders only the selected family dependency inputs in its source preview', function (string $family, string $prefix): void {
    $this->seed(RehPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo(['reh.view', 'reh.sensitive']);
    $unit = RehUnit::factory()->create(['source' => [...rehScoringSource(), ...rehScoringDisplacedSource()]]);
    $html = $this->actingAs($user)->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $template = $xpath->query('//div[@data-score-family-panel="'.$family.'"]//details[@data-score-row="dependency"]//template[@data-score-source-content]')->item(0);
    expect($template)->not->toBeNull();
    $fields = [];
    foreach ($xpath->query('.//*[@data-indicator-field]', $template) as $field) {
        $fields[] = $field->getAttribute('data-indicator-field');
    }
    expect($fields)->toBe(array_map(fn (string $suffix): string => $prefix.$suffix, ['males_under5', 'males_age5_17', 'males_over60', 'females_under5', 'females_age5_17', 'females_over60', 'males_age18_60', 'females_age18_60']));
    $this->get(route('reh.index'))->assertOk()->assertSee('id="reh-score-source-dialog"', false)->assertSee('العودة للتقييم');
})->with([['owner', 'household_'], ['other', 'nonowner_']]);

it('keeps the engineering source preview limited to the indicator and distinguishes original reviewed and missing quantities', function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('reh.view');
    $unit = RehUnit::factory()->create(['source' => rehScoringSource()]);
    $kitchen = collect(app(RehScoring::class)->engineering($unit)['rows'])->firstWhere('id', 'kitchen');
    $source = $unit->source;
    $source[$kitchen['inputs'][0]['field']] = 5;
    $source[$kitchen['inputs'][1]['field']] = null;
    $unit->update(['source' => $source, 'quantity_overrides' => [$kitchen['inputs'][0]['field'] => 0]]);
    $html = $this->actingAs($user)->getJson(route('reh.units.show', $unit))->assertOk()->json('html');
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $template = $xpath->query('//details[@data-score-row="kitchen"]//template[@data-score-source-content]')->item(0);
    $fields = $xpath->query('.//*[@data-indicator-field]', $template);
    expect($fields->length)->toBe(4)
        ->and($template->textContent)->not->toContain('توريد وتصنيع وتركيب أسطح مطابخ ومجالي بسمك')
        ->and($fields->item(0)->textContent)->toContain('الكمية الميدانية: 5', 'الكمية المراجعة: 0')
        ->and($fields->item(1)->textContent)->toContain('غير مسجل', 'لم تُعدّل', 'لا توجد أعمال مدرجة');
    expect($xpath->query('//template[@data-score-source-content]//*[@data-indicator-field="owner_age"]')->length)->toBe(0)
        ->and($xpath->query('//template[@data-score-source-content]//*[@data-indicator-field="household_chronic_count"]')->length)->toBe(0);
});

function rehPrioritySource(string $priority): array
{
    $source = rehScoringSource();
    if ($priority === 'unclassified') {
        return [...$source, 'owner_age' => null];
    }
    if ($priority === 'low') {
        return $source;
    }
    foreach (array_keys(rehScoringBlankBoq()) as $field) {
        $source[$field] = 20;
    }
    $source['unit_needs_intervention'] = 'yes';
    if ($priority === 'medium') {
        return $source;
    }
    $source = [...$source, 'other_members_have_disability' => 'yes', 'household_has_stable_income' => 'no'];
    foreach (['other_vision_difficulty_count', 'other_hearing_difficulty_count', 'other_walking_difficulty_count', 'other_memory_difficulty_count', 'other_selfcare_diff_count', 'other_communication_diff_count'] as $field) {
        $source[$field] = 2;
    }

    return $priority === 'high' ? $source : [...$source, 'household_chronic_count' => 2, 'household_pregnant_lactating' => 2, 'household_females_under5' => 4, 'unaccompanied_child_count' => 2, 'owner_has_disability' => 'yes'];
}

it('filters each calculated priority and unclassified units consistently in results counts and exports', function (string $priority): void {
    $this->seed(RehPermissionsSeeder::class);
    $officer = User::factory()->create();
    $officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    foreach ([...array_keys(RehScoring::PRIORITIES), 'unclassified'] as $level) {
        $unit = RehUnit::factory()->create(['source' => rehPrioritySource($level), 'code' => 'PRIORITY-'.$level]);
        expect(app(RehScoring::class)->calculate($unit)['priority'] ?? 'unclassified')->toBe($level);
    }
    $this->actingAs($officer);
    $html = $this->getJson(route('reh.data', ['tab' => 'units', 'priority' => $priority]))->assertOk()->json('html');
    $csv = $this->get(route('reh.export', ['priority' => $priority]))->assertOk()->streamedContent();
    expect($html)->toContain('1 وحدة', 'PRIORITY-'.$priority);
    $rows = array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));
    expect($rows)->toHaveCount(2)->and($rows[1][15])->toBe('PRIORITY-'.$priority);
    foreach (array_diff([...array_keys(RehScoring::PRIORITIES), 'unclassified'], [$priority]) as $other) {
        expect($html)->not->toContain('PRIORITY-'.$other.'<');
    }
})->with([...array_keys(RehScoring::PRIORITIES), 'unclassified']);

it('applies priority before pagination combines survey status and refreshes changed scores immediately', function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $officer = User::factory()->create();
    $officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
    RehUnit::factory()->count(21)->create(['source' => rehPrioritySource('medium')]);
    $changed = RehUnit::factory()->create(['source' => rehPrioritySource('low'), 'code' => 'PRIORITY-CHANGED']);
    $pending = RehUnit::factory()->create(['source' => rehPrioritySource('medium'), 'code' => 'PRIORITY-PENDING']);
    $pending->building->update(['source' => ['Field_status' => 'Not_Completed']]);
    $this->actingAs($officer);
    $html = $this->getJson(route('reh.data', ['tab' => 'units', 'priority' => 'medium', 'page' => 2]))->assertOk()->json('html');
    expect($html)->toContain('21 وحدة')->not->toContain('PRIORITY-CHANGED', 'PRIORITY-PENDING');
    $csv = $this->get(route('reh.export', ['priority' => 'medium']))->assertOk()->streamedContent();
    expect(array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF")))))->toHaveCount(22);
    $html = $this->getJson(route('reh.data', ['tab' => 'units', 'priority' => 'medium', 'survey_status' => 'not_completed']))->assertOk()->json('html');
    expect($html)->toContain('1 وحدة', 'PRIORITY-PENDING');
    $changed->update(['source' => rehPrioritySource('medium')]);
    $html = $this->getJson(route('reh.data', ['tab' => 'units', 'priority' => 'medium']))->assertOk()->json('html');
    expect($html)->toContain('22 وحدة', 'PRIORITY-CHANGED');
});

it('validates priority and restricts its controls and filtering to users allowed to see final scores', function (): void {
    $this->seed(RehPermissionsSeeder::class);
    $reader = User::factory()->create();
    $reader->givePermissionTo(['reh.view', 'reh.export']);
    $this->actingAs($reader)->get(route('reh.index'))->assertOk()->assertDontSee('id="reh-priority"', false);
    $this->getJson(route('reh.data', ['priority' => 'low']))->assertForbidden();
    $this->get(route('reh.export', ['priority' => 'low']))->assertForbidden();
    $reader->givePermissionTo('reh.scores.view');
    $this->get(route('reh.index'))->assertOk()->assertSee('id="reh-priority"', false);
    $this->getJson(route('reh.data', ['priority' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('priority');
});

it('scores unused nullable boq fields as no work within an existing assessment and preserves raw data', function (): void {
    $unit = rehScoringUnit([
        ...rehScoringBlankBoq(), 'unit_needs_intervention' => 'yes',
        'boq_12_1_water_tank_1000l_qty' => 1,
        'boq_2_3_partition_wall_qty' => 10.5, 'boq_2_1_pallet_wall_qty' => 4.5, 'boq_5_3_roof_tarp_drain_qty' => 1,
    ]);
    $original = $unit->source;
    $score = app(RehScoring::class)->calculate($unit);
    expect($score['engineering']['calculated_count'])->toBe(12)
        ->and($score['engineering']['pending_count'])->toBe(0)
        ->and($score['engineering']['total'])->toBe(8)
        ->and(rehScoreRow($score, 'engineering', 'kitchen')['points'])->toBe(0)
        ->and(rehScoreRow($score, 'engineering', 'internal_wall')['points'])->toBe(2)
        ->and($score['total'])->toBe(10)
        ->and($unit->source)->toBe($original)
        ->and(rehScoreRow($score, 'engineering', 'kitchen')['inputs'][0]['quantity'])->toBeNull()
        ->and(rehScoreRow($score, 'engineering', 'kitchen')['inputs'][0]['not_requested'])->toBeTrue();
});

it('keeps an entirely blank engineering assessment pending unless no intervention is explicitly recorded', function (?string $needsIntervention, ?int $expected): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit([...rehScoringBlankBoq(), 'unit_needs_intervention' => $needsIntervention]));
    expect($score['engineering']['total'])->toBe($expected)
        ->and($score['engineering']['pending_count'])->toBe($expected === null ? 12 : 0);
})->with([['yes', null], [null, null], ['no', 0]]);

it('does not turn a missing source schema into a zero assessment', function (): void {
    $score = app(RehScoring::class)->calculate(new RehUnit(['source' => ['unit_needs_intervention' => 'no']]));
    expect($score['engineering']['total'])->toBeNull()->and($score['engineering']['pending_count'])->toBe(12);
});

it('does not mistake one zero entry for a completed otherwise blank assessment', function (): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit([...rehScoringBlankBoq(), 'unit_needs_intervention' => 'yes', 'boq_12_1_water_tank_1000l_qty' => 0]));
    expect($score['engineering']['total'])->toBeNull();
});

it('respects saved zero overrides while flagging invalid quantities in a sparse assessment', function (): void {
    $unit = rehScoringUnit([...rehScoringBlankBoq(), 'boq_12_1_water_tank_1000l_qty' => 1]);
    $unit->quantity_overrides = ['boq_12_1_water_tank_1000l_qty' => 0];
    expect(app(RehScoring::class)->engineering($unit)['total'])->toBe(0);
    $unit->source = [...$unit->source, 'boq_2_3_partition_wall_qty' => -1];
    $score = app(RehScoring::class)->calculate($unit);
    expect(rehScoreRow($score, 'engineering', 'internal_wall')['status'])->toBe('invalid')
        ->and($score['engineering']['total'])->toBeNull();
});

it('never uses the engineering blank rule to fill required social answers', function (): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit([...rehScoringBlankBoq(), 'boq_12_1_water_tank_1000l_qty' => 1, 'household_chronic_count' => null]));
    expect($score['engineering']['total'])->toBe(2)
        ->and(rehScoreRow($score, 'owner', 'chronic')['status'])->toBe('missing')
        ->and($score['total'])->toBeNull()->and($score['priority'])->toBeNull();
});

it('calculates dependency from all eight age counts with exact ratio boundaries', function (int $dependents, int $workingAge, ?int $points, string $status): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['household_males_age5_17' => $dependents, 'household_males_age18_60' => $workingAge]));
    expect(rehScoreRow($score, 'owner', 'dependency')['points'])->toBe($points)
        ->and(rehScoreRow($score, 'owner', 'dependency')['status'])->toBe($status);
    if ($points === null) {
        expect($score['total'])->toBeNull()->and($score['priority'])->toBeNull();
    }
})->with([[0, 2, 0, 'calculated'], [2, 2, 0, 'calculated'], [3, 2, 2, 'calculated'], [4, 2, 2, 'calculated'], [5, 2, 4, 'calculated'], [3, 0, null, 'invalid'], [0, 0, null, 'invalid']]);

it('holds the final score and priority for missing required age counts without treating them as zero', function (): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['household_females_age5_17' => null]));
    expect(rehScoreRow($score, 'owner', 'dependency')['status'])->toBe('missing')
        ->and($score['social_total'])->toBeNull()->and($score['total'])->toBeNull()->and($score['priority'])->toBeNull();
});

it('scores displaced households as one aggregate and selects the higher completed social score', function (): void {
    $unit = rehScoringUnit(rehScoringDisplacedSource());
    $score = app(RehScoring::class)->calculate($unit);
    expect($score['owner']['total'])->toBe(5)
        ->and($score['other']['total'])->toBe(22)
        ->and($score['social_total'])->toBe(22)->and($score['selected_family'])->toBe('other')
        ->and($score['total'])->toBe(22)->and($score['priority'])->toBe('low')
        ->and(rehScoreRow($score, 'other', 'under5')['points'])->toBe(3)
        ->and(rehScoreRow($score, 'other', 'chronic')['points'])->toBe(3)
        ->and(rehScoreRow($score, 'other', 'dependency')['points'])->toBe(4)
        ->and(rehScoreRow($score, 'other', 'hosting')['points'])->toBe(3);
    $unit->source = [...$unit->source, 'displaced_families_count' => 9];
    expect(app(RehScoring::class)->calculate($unit)['total'])->toBe(22);
});

it('uses hosted representative fields and never falls back to owner demographics for the other group', function (): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit([
        ...rehScoringDisplacedSource(), 'current_unit_occupancy' => 'owner_family', 'hosts_other_households' => 'yes',
        'hosted_individuals_count' => 15, 'hosted_age' => 61, 'hosted_sex' => 'female', 'owner_age' => 17,
        'nonowner_females_over60' => 1, 'nonowner_breadwinner_f' => 'owner',
    ]));
    expect(rehScoreRow($score, 'other', 'age')['points'])->toBe(3)
        ->and(rehScoreRow($score, 'other', 'minor')['points'])->toBe(0)
        ->and(rehScoreRow($score, 'other', 'female')['points'])->toBe(3)
        ->and(rehScoreRow($score, 'other', 'elders')['points'])->toBe(0);
});

it('does not select a lower completed owner score when the other required group is incomplete', function (): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit([...rehScoringDisplacedSource(), 'nonowner_has_stable_income' => null]));
    expect($score['owner']['total'])->not->toBeNull()
        ->and($score['other']['total'])->toBeNull()->and($score['social_total'])->toBeNull()
        ->and($score['selected_family'])->toBeNull()->and($score['total'])->toBeNull();
});

it('does not infer an individual representative disability from aggregated health counts', function (): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit([...rehScoringDisplacedSource(), 'nonowner_has_disability' => 'yes', 'nonowner_vision_count' => 2, 'nonowner_disability_count' => 2]));
    expect(rehScoreRow($score, 'other', 'disability_vision')['points'])->toBe(2)
        ->and(rehScoreRow($score, 'other', 'head_disability')['status'])->toBe('missing')
        ->and($score['total'])->toBeNull();
});

it('respects conditional required health counts and rejects contradictory answers', function (): void {
    $source = [...rehScoringDisplacedSource(), 'nonowner_has_chronic' => 'no', 'nonowner_chronic_count' => null, 'nonowner_vision_count' => null];
    $score = app(RehScoring::class)->calculate(rehScoringUnit($source));
    expect(rehScoreRow($score, 'other', 'chronic')['points'])->toBe(0)
        ->and(rehScoreRow($score, 'other', 'disability_vision')['points'])->toBe(0)
        ->and($score['total'])->not->toBeNull();
    $score = app(RehScoring::class)->calculate(rehScoringUnit([...$source, 'nonowner_chronic_count' => 2]));
    expect(rehScoreRow($score, 'other', 'chronic')['status'])->toBe('invalid')->and($score['total'])->toBeNull();
});

it('uses the published priority boundaries without normalizing the achievable 97 points to 100', function (int $total, string $priority): void {
    expect(app(RehScoring::class)->priority($total))->toBe($priority);
})->with([[0, 'low'], [29, 'low'], [30, 'medium'], [54, 'medium'], [55, 'high'], [75, 'high'], [76, 'very_high'], [97, 'very_high']]);

it('scores external wall decimal thresholds by their own unit groups', function (float $quantity, int $points): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['boq_2_1_pallet_wall_qty' => $quantity, 'boq_2_5_used_block_20cm_qty' => 0.5]));
    expect(rehScoreRow($score, 'engineering', 'external_wall')['points'])->toBe($points);
})->with([[4.5, 1], [4.999, 1], [5.0, 3], [10.5, 3], [10.999, 3], [11.0, 4]]);

it('computes a final score from required inputs and ignores untrusted source scores', function (): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['social_score' => 60, 'total_score' => 100, 'priority' => 'very_high']));
    expect($score['owner']['rows'])->toHaveCount(23)
        ->and(array_sum(array_column($score['owner']['rows'], 'maximum')))->toBe(60)
        ->and($score['engineering']['rows'])->toHaveCount(12)
        ->and($score['engineering']['total'])->toBe(0)
        ->and($score['owner']['calculated_count'])->toBe(23)
        ->and($score['owner']['subtotal'])->toBe(2)
        ->and($score['other'])->toBeNull()
        ->and($score['total'])->toBe(2)->and($score['priority'])->toBe('low')
        ->and(rehScoreRow($score, 'owner', 'dependency')['points'])->toBe(0);
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

it('deduplicates known risks and flags missing representative data without inventing it', function (): void {
    $unit = rehScoringUnit(['hosts_other_households' => 'yes', 'hosted_individuals_count' => 4, 'nonowner_protection_risks' => 'none', 'household_protection_risks' => 'debt debt']);
    $score = app(RehScoring::class)->calculate($unit);
    expect(rehScoreRow($score, 'owner', 'protection')['points'])->toBe(2)
        ->and(rehScoreRow($score, 'other', 'protection')['points'])->toBe(0)
        ->and(rehScoreRow($score, 'other', 'head_disability')['status'])->toBe('missing')
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

it('resolves binary categories with a positive quantity but keeps absent source fields missing', function (): void {
    $unit = new RehUnit(['source' => ['boq_12_1_water_tank_1000l_qty' => 1]]);
    $score = app(RehScoring::class)->calculate($unit);
    expect(rehScoreRow($score, 'engineering', 'tank')['points'])->toBe(2)
        ->and(rehScoreRow($score, 'engineering', 'kitchen')['status'])->toBe('missing')
        ->and($score['engineering']['total'])->toBeNull();
});

it('handles continuous internal wall thresholds without rounding quantities', function (mixed $quantity, ?int $expected, string $status): void {
    $score = app(RehScoring::class)->calculate(rehScoringUnit(['boq_2_3_partition_wall_qty' => $quantity]));
    expect(rehScoreRow($score, 'engineering', 'internal_wall')['points'])->toBe($expected)
        ->and(rehScoreRow($score, 'engineering', 'internal_wall')['status'])->toBe($status);
})->with([[0, 0, 'calculated'], [1, 1, 'calculated'], [4, 1, 'calculated'], [5, 2, 'calculated'], [10, 2, 'calculated'], [11, 4, 'calculated'], [0.5, 1, 'calculated'], [4.5, 1, 'calculated'], [4.999, 1, 'calculated'], [5.5, 2, 'calculated'], [10.5, 2, 'calculated'], [10.999, 2, 'calculated'], [null, 0, 'calculated'], ['', 0, 'calculated'], [-1, null, 'invalid'], ['bad', null, 'invalid']]);

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
    expect($response->json('html'))->toContain('data-scoring', 'أولوية منخفضة', 'نسبة الإعالة', 'data-final-score="2"')->not->toContain('قواعد بانتظار الإدارة');
    expect($this->getJson(route('reh.data', ['tab' => 'units']))->assertOk()->json('html'))->toContain('reh-final-summary', 'data-final-score="2"', 'reh-priority-low', 'أولوية منخفضة');
    $csv = $this->get(route('reh.export'))->assertOk()->streamedContent();
    $csvRows = array_map('str_getcsv', explode("\n", trim($csv)));
    expect($csvRows[1][12])->toBe('2')->and($csvRows[1][13])->toBe('أولوية منخفضة');
    $response = $this->postJson(route('reh.units.review', $unit), ['action' => 'quantities', 'version' => 1, 'note' => 'تدقيق الحائط الداخلي', 'quantities' => ['boq_2_3_partition_wall_qty' => 11]])->assertOk();
    expect($response->json('html'))->toContain('data-engineering-subtotal="4"', 'قبل 0', 'بعد 4');
    $changes = $unit->reviews()->latest()->first()->changes['scoring'];
    expect($changes['before']['engineering']['subtotal'])->toBe(0)
        ->and($changes['after']['engineering']['subtotal'])->toBe(4)
        ->and($changes['after']['total'])->toBe(6)
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
    expect($html)->toContain('data-score-panel="engineering"')->not->toContain('data-score-panel="social"', 'إعاقة المالك', 'مخاطر الحماية', 'أسرة المالك · نقاط محسومة', 'data-priority=');
    $csv = $this->get(route('reh.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('نقاط هندسية محسومة', 'محجوب', RehScoring::VERSION)->not->toContain('نقاط اجتماعية', 'أولوية منخفضة');
});

it('renders a distinct priority badge for every priority and the pending state', function (?string $priority, string $style, string $label): void {
    $view = $this->view('reh::partials.priority-badge', ['priority' => $priority]);
    $view->assertSee('reh-priority-'.$style, false)->assertSee('data-priority="'.$style.'"', false)->assertSee($label);
})->with([
    ['very_high', 'very_high', 'أولوية عالية جدًا'],
    ['high', 'high', 'أولوية عالية'],
    ['medium', 'medium', 'أولوية متوسطة'],
    ['low', 'low', 'أولوية منخفضة'],
    [null, 'pending', 'الأولوية غير مصنفة'],
    ['unknown', 'pending', 'الأولوية غير مصنفة'],
]);

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
