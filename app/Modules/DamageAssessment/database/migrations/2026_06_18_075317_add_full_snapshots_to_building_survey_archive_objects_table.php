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
        Schema::table('building_survey_archive_objects', function (Blueprint $table) {
            if (! Schema::hasColumn('building_survey_archive_objects', 'building_snapshot')) {
                $table->json('building_snapshot')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('building_survey_archive_objects', 'housing_unit_snapshot')) {
                $table->json('housing_unit_snapshot')->nullable()->after('building_snapshot');
            }

            if (! Schema::hasColumn('building_survey_archive_objects', 'committee_decision_snapshot')) {
                $table->json('committee_decision_snapshot')->nullable()->after('housing_unit_snapshot');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('building_survey_archive_objects', function (Blueprint $table) {
            foreach (['committee_decision_snapshot', 'housing_unit_snapshot', 'building_snapshot'] as $column) {
                if (Schema::hasColumn('building_survey_archive_objects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
