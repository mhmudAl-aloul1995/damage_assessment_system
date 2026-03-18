<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Assigned Assessment Users Table
        Schema::create('assigned_assessment_users', function (Blueprint $table) {
            $table->id();
            // FIXED: nullable() must come BEFORE constrained() for nullOnDelete() to work
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['eng', 'lawyer', 'other'])->default('eng');
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->timestamps();
        });

        // 2. Edit Assessments Table
        Schema::create('edit_assessments', function (Blueprint $table) {
            $table->id();
            $table->text('global_id');
            $table->enum('type', ['building_table', 'housing_table'])->default('building_table');
            $table->string('field_name');
            $table->text('field_value')->nullable();
            // FIXED: Consistency in nullable placement
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });


        Schema::create('assessment_statuses', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            // pending, assigned_to_engineer, rejected_by_engineer ...

            $table->string('label_en');
            $table->string('label_ar');

            $table->enum('stage', [
                'system',
                'engineer',
                'lawyer',
                'team_leader'
            ]);

            $table->integer('order_step')->default(0);

            $table->timestamps();
        });
        Schema::create('building_statuses', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_id')->constrained()->cascadeOnDelete();

            $table->foreignId('status_id')
                ->constrained('assessment_statuses')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->enum('type', ['eng', 'lawyer']);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['building_id', 'type']);
        });
        Schema::create('building_status_histories', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('assessment_status_id')
                ->constrained('assessment_statuses');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('assigned_assessment_users');
        Schema::dropIfExists('building_statuses');
        Schema::dropIfExists('assessment_statuses');
        Schema::dropIfExists('edit_assessments');
        Schema::dropIfExists('building_status_histories');
    }
};
