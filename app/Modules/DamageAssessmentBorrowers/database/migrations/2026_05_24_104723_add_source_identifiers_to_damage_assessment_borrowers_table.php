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
        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->string('source_uuid')->nullable()->unique()->after('submitted_by_name');
            $table->unsignedBigInteger('source_submission_id')->nullable()->index()->after('source_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->dropUnique(['source_uuid']);
            $table->dropIndex(['source_submission_id']);
            $table->dropColumn(['source_uuid', 'source_submission_id']);
        });
    }
};
