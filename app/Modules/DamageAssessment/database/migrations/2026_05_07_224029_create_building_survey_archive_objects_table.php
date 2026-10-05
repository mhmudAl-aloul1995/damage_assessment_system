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
        Schema::create('building_survey_archive_objects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('building_objectid')->index();
            $table->string('building_globalid')->nullable()->index();
            $table->foreignId('return_request_id')->constrained('building_survey_return_requests')->cascadeOnDelete();
            $table->foreignId('archived_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('building_survey_archive_objects');
    }
};
