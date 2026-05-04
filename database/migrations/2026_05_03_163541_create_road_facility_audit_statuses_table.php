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
        Schema::create('road_facility_audit_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('road_facility_survey_id')->nullable();
            $table->unsignedBigInteger('objectid')->nullable()->index();
            $table->string('globalid')->index();
            $table->foreignId('status_id')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('globalid', 'road_audit_status_globalid_unique');
            $table->foreign('road_facility_survey_id', 'road_audit_status_survey_fk')->references('id')->on('road_facility_surveys')->cascadeOnDelete();
            $table->foreign('status_id', 'road_audit_status_status_fk')->references('id')->on('inf_audit_statuses')->nullOnDelete();
            $table->foreign('assigned_to', 'road_audit_status_assignee_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'road_audit_status_updater_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('road_facility_audit_statuses');
    }
};
