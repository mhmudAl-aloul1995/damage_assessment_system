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
        Schema::create('building_survey_return_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('building_survey_return_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['created', 'approved', 'rejected', 'completed']);
            $table->enum('step', ['field_engineer', 'team_leader', 'area_manager', 'system']);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('building_survey_return_request_logs');
    }
};
