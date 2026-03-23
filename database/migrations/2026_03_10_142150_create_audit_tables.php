<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('assigned_assessment_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('edit_assessments', function (Blueprint $table) {
            $table->id();
            $table->text('global_id');
            $table->enum('type', ['building_table', 'housing_table'])->default('building_table');
            $table->string('field_name');
            $table->text('field_value')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('assessment_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
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

            $table->foreignId('building_id')
                ->constrained('buildings')
                ->references('objectid')
                ->cascadeOnDelete();

            $table->foreignId('status_id')
                ->constrained('assessment_statuses')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('type');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['building_id', 'type']);
        });

        Schema::create('building_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('building_id')
                ->constrained('buildings')
                ->references('objectid')
                ->cascadeOnDelete();

            $table->foreignId('status_id')
                ->constrained('assessment_statuses')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->string('type');

            $table->timestamps();
        });

        Schema::create('housing_statuses', function (Blueprint $table) {
            $table->id();

            // غيّر housing_units إذا كان اسم جدولك مختلف
            $table->foreign('housing_id')
                ->references('objectid')
                ->on('housing_units')
                ->cascadeOnDelete();

            $table->foreignId('status_id')
                ->constrained('assessment_statuses')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('type');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['housing_id', 'type']);
        });

        Schema::create('housing_status_histories', function (Blueprint $table) {
            $table->id();

            // غيّر housing_units إذا كان اسم جدولك مختلف
            $table->foreign('housing_id')
                ->references('objectid')
                ->on('housing_units')
                ->cascadeOnDelete();

            $table->foreignId('status_id')
                ->constrained('assessment_statuses')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->string('type');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('housing_status_histories');
        Schema::dropIfExists('housing_statuses');
        Schema::dropIfExists('building_status_histories');
        Schema::dropIfExists('building_statuses');
        Schema::dropIfExists('assessment_statuses');
        Schema::dropIfExists('edit_assessments');
        Schema::dropIfExists('assigned_assessment_users');
    }
};
