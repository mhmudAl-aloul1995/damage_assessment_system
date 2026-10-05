<?php

use App\Modules\DamageAssessmentBorrowers\Services\BorrowerDuplicateMergeService;
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
        app(BorrowerDuplicateMergeService::class)->merge();

        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->unique('borrower_id_number', 'damage_assessment_borrowers_borrower_id_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->dropUnique('damage_assessment_borrowers_borrower_id_number_unique');
        });
    }
};
