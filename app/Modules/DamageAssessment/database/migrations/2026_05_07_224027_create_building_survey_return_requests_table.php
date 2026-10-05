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
        Schema::create('building_survey_return_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('building_id')->nullable()->index();
            $table->unsignedBigInteger('building_objectid')->index();
            $table->string('building_globalid')->nullable()->index();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('area_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('current_step', [
                'field_engineer',
                'team_leader',
                'area_manager',
                'completed',
                'rejected',
            ])->default('team_leader');
            $table->enum('status', [
                'pending',
                'approved_by_team_leader',
                'approved_by_area_manager',
                'rejected',
                'completed',
            ])->default('pending');
            $table->text('reason')->nullable();
            $table->text('team_leader_notes')->nullable();
            $table->text('area_manager_notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('team_leader_approved_at')->nullable();
            $table->timestamp('area_manager_approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('building_survey_return_requests');
    }
};
