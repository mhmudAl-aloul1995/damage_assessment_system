<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('warda_buildings');
    Schema::dropIfExists('warda_units');

    Schema::create('warda_buildings', function (Blueprint $table): void {
        $table->text('globalid');
        $table->string('audit_status')->nullable();
    });

    Schema::create('warda_units', function (Blueprint $table): void {
        $table->text('globalid');
        $table->string('audit_status')->nullable();
    });

    DB::table('buildings')->insert([
        [
            'objectid' => 1001,
            'globalid' => 'building-pending',
            'building_damage_status' => 'source_building',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'objectid' => 1002,
            'globalid' => 'building-approved',
            'building_damage_status' => 'approved_source_building',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('housing_units')->insert([
        [
            'objectid' => 2001,
            'globalid' => 'housing-pending',
            'parentglobalid' => 'building-pending',
            'unit_damage_status' => 'source_unit',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'objectid' => 2002,
            'globalid' => 'housing-approved',
            'parentglobalid' => 'building-approved',
            'unit_damage_status' => 'approved_source_unit',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('warda_buildings')->insert([
        ['globalid' => 'building-pending', 'audit_status' => 'Pending'],
        ['globalid' => 'building-approved', 'audit_status' => 'Accepted'],
    ]);

    DB::table('warda_units')->insert([
        ['globalid' => 'housing-pending', 'audit_status' => 'Pending'],
        ['globalid' => 'housing-approved', 'audit_status' => 'Accepted'],
    ]);

    DB::table('edit_assessments')->insert([
        [
            'id' => 100,
            'global_id' => 'building-pending',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => 'previous_building_edit',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ],
        [
            'id' => 101,
            'global_id' => 'building-pending',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 102,
            'global_id' => 'building-pending',
            'type' => 'building_table',
            'field_name' => 'building_name',
            'field_value' => 'kept value',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 103,
            'global_id' => 'building-approved',
            'type' => 'building_table',
            'field_name' => 'building_damage_status',
            'field_value' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 104,
            'global_id' => 'housing-pending',
            'type' => 'housing_table',
            'field_name' => 'unit_damage_status',
            'field_value' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 105,
            'global_id' => 'housing-approved',
            'type' => 'housing_table',
            'field_name' => 'unit_damage_status',
            'field_value' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('audited_buildings')->insert([
        'objectid' => 1001,
        'globalid' => 'building-pending',
        'building_damage_status' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('audited_housing_units')->insert([
        'objectid' => 2001,
        'globalid' => 'housing-pending',
        'parentglobalid' => 'building-pending',
        'unit_damage_status' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('reports matching null pending edits without deleting in dry run', function (): void {
    $this->artisan('audit-edits:delete-null-pending', ['--dry-run' => true])
        ->assertSuccessful();

    expect(DB::table('edit_assessments')->whereIn('id', [101, 104])->count())->toBe(2);
    expect(DB::table('audit_edit_deletion_batches')->count())->toBe(0);
});

it('deletes null pending building and housing edits with backup and restores them', function (): void {
    $this->artisan('audit-edits:delete-null-pending')
        ->assertSuccessful();

    $batchId = DB::table('audit_edit_deletion_batches')->value('id');

    expect($batchId)->not->toBeNull();
    expect(DB::table('edit_assessments')->whereIn('id', [101, 104])->count())->toBe(0);
    expect(DB::table('edit_assessments')->whereIn('id', [100, 102, 103, 105])->count())->toBe(4);
    expect(DB::table('audit_edit_deletion_items')->where('batch_id', $batchId)->count())->toBe(2);
    expect(DB::table('audited_buildings')->where('globalid', 'building-pending')->value('building_damage_status'))->toBe('previous_building_edit');
    expect(DB::table('audited_housing_units')->where('globalid', 'housing-pending')->value('unit_damage_status'))->toBe('source_unit');

    $this->artisan('audit-edits:restore-deleted', ['batch' => $batchId])
        ->assertSuccessful();

    expect(DB::table('edit_assessments')->whereIn('id', [101, 104])->count())->toBe(2);
    expect(DB::table('audit_edit_deletion_items')->where('batch_id', $batchId)->whereNull('restored_at')->count())->toBe(0);
    expect(DB::table('audit_edit_deletion_batches')->where('id', $batchId)->value('restored_at'))->not->toBeNull();
    expect(DB::table('audited_buildings')->where('globalid', 'building-pending')->value('building_damage_status'))->toBeNull();
    expect(DB::table('audited_housing_units')->where('globalid', 'housing-pending')->value('unit_damage_status'))->toBeNull();
});
