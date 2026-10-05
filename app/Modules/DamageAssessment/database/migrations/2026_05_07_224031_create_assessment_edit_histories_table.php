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
        Schema::create('assessment_edit_histories', function (Blueprint $table) {
            $table->id();
            $table->string('global_id')->index();
            $table->unsignedBigInteger('objectid')->nullable()->index();
            $table->enum('type', ['building_table', 'housing_table']);
            $table->string('field_name')->index();
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('edit_assessment_id')->nullable()->constrained('edit_assessments')->nullOnDelete();
            $table->foreignId('return_request_id')->nullable()->constrained('building_survey_return_requests')->nullOnDelete();
            $table->string('source')->nullable()->default('manual');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_edit_histories');
    }
};
