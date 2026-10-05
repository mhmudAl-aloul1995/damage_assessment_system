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
        Schema::create('public_building_audit_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_building_survey_id');
            $table->unsignedBigInteger('objectid')->nullable()->index();
            $table->string('globalid')->nullable()->index();
            $table->foreignId('status_id')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('public_building_survey_id', 'pb_audit_status_survey_unique');
            $table->foreign('public_building_survey_id', 'pb_audit_status_survey_fk')->references('id')->on('public_building_surveys')->cascadeOnDelete();
            $table->foreign('status_id', 'pb_audit_status_status_fk')->references('id')->on('inf_audit_statuses')->nullOnDelete();
            $table->foreign('assigned_to', 'pb_audit_status_assignee_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'pb_audit_status_updater_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_building_audit_statuses');
    }
};
