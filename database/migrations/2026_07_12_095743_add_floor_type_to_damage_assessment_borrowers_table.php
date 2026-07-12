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
            if (! Schema::hasColumn('damage_assessment_borrowers', 'loan_unit_floor_type')) {
                $table->string('loan_unit_floor_type', 30)->nullable()->after('loan_unit_area');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            if (Schema::hasColumn('damage_assessment_borrowers', 'loan_unit_floor_type')) {
                $table->dropColumn('loan_unit_floor_type');
            }
        });
    }
};
