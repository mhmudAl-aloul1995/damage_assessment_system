<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cso_survey_organizations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('objectid')->nullable()->unique();
            $table->string('globalid')->nullable()->index();
            $table->string('parentglobalid')->nullable()->index();
            $table->unsignedInteger('repeat_index')->nullable();
            $table->string('organization_name_en')->nullable()->index();
            $table->string('organization_name_ar')->nullable()->index();
            $table->string('organization_acronym')->nullable()->index();
            $table->string('operational_status')->nullable()->index();
            $table->timestamp('creationdate')->nullable()->index();
            $table->string('creator')->nullable();
            $table->timestamp('editdate')->nullable();
            $table->string('editor')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('cso_survey_units', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('objectid')->nullable()->unique();
            $table->string('globalid')->nullable()->index();
            $table->string('parentglobalid')->nullable()->index();
            $table->unsignedInteger('repeat_index')->nullable();
            $table->string('unit_name')->nullable()->index();
            $table->integer('unit_floor_number')->nullable();
            $table->integer('unit_number')->nullable();
            $table->string('unit_damage_status')->nullable()->index();
            $table->timestamp('creationdate')->nullable()->index();
            $table->string('creator')->nullable();
            $table->timestamp('editdate')->nullable();
            $table->string('editor')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cso_survey_units');
        Schema::dropIfExists('cso_survey_organizations');
    }
};
