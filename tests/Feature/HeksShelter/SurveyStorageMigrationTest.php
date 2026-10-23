<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('moves existing phase three answers and audit values into columns and related tables', function (): void {
    $original = config('database.default');
    config(['database.connections.heks_storage_fixture' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
    app('db')->setDefaultConnection('heks_storage_fixture');
    try {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('heks_shelter_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->text('answers');
        });
        Schema::create('heks_shelter_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('case_id');
            $table->string('action');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('note')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });
        Schema::create('mobile_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('form_key');
            $table->text('changes')->nullable();
        });
        $connection = app('db')->connection();
        $answers = ['name' => 'بيانات محفوظة', 'identity_number' => '400000001', 'family_total' => 6, 'unit_area' => 81.123456, 'recommendations' => ['repair', 'guarantor']];
        $connection->table('heks_shelter_cases')->insert(['id' => 1, 'code' => 'OLD-3', 'answers' => json_encode($answers)]);
        $connection->table('heks_shelter_events')->insert(['id' => 1, 'case_id' => 1, 'action' => 'survey', 'details' => json_encode(['before' => [], 'after' => $answers])]);
        $migration = require base_path('app/Modules/HeksShelter/database/migrations/2026_10_22_235957_create_heks_survey_designer_tables.php');
        $migration->up();
        $migration->up();
        expect(Schema::hasColumn('heks_shelter_cases', 'answers'))->toBeFalse();
        $row = $connection->table('heks_shelter_cases')->find(1);
        expect($row->sf_name)->toBe('بيانات محفوظة')->and((int) $row->sf_family_total)->toBe(6)->and((float) $row->sf_unit_area)->toBe(81.123456);
        expect($connection->table('heks_survey_selections')->where('case_id', 1)->orderBy('id')->pluck('value')->all())->toBe(['repair', 'guarantor']);
        expect($connection->table('heks_survey_answer_changes')->where('field_path', 'identity_number')->value('new_value'))->toBe('400000001');
        expect(json_decode($connection->table('heks_shelter_events')->value('details'), true))->not->toHaveKeys(['before', 'after']);
    } finally {
        app('db')->setDefaultConnection($original);
        app('db')->purge('heks_storage_fixture');
    }
});
