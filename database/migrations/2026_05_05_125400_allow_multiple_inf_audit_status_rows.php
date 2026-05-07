<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return count($rows) > 0;
    }

    public function up(): void
    {
        if (
            Schema::hasTable('public_building_audit_statuses') &&
            Schema::hasColumn('public_building_audit_statuses', 'public_building_survey_id')
        ) {
            if (! $this->indexExists('public_building_audit_statuses', 'pb_audit_status_survey_id_index')) {
                Schema::table('public_building_audit_statuses', function (Blueprint $table) {
                    $table->index('public_building_survey_id', 'pb_audit_status_survey_id_index');
                });
            }

            if ($this->indexExists('public_building_audit_statuses', 'pb_audit_status_survey_unique')) {
                Schema::table('public_building_audit_statuses', function (Blueprint $table) {
                    $table->dropUnique('pb_audit_status_survey_unique');
                });
            }
        }

        if (
            Schema::hasTable('road_facility_audit_statuses') &&
            Schema::hasColumn('road_facility_audit_statuses', 'road_facility_survey_id')
        ) {
            if (! $this->indexExists('road_facility_audit_statuses', 'road_audit_status_survey_id_index')) {
                Schema::table('road_facility_audit_statuses', function (Blueprint $table) {
                    $table->index('road_facility_survey_id', 'road_audit_status_survey_id_index');
                });
            }

            if ($this->indexExists('road_facility_audit_statuses', 'road_audit_status_survey_unique')) {
                Schema::table('road_facility_audit_statuses', function (Blueprint $table) {
                    $table->dropUnique('road_audit_status_survey_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('public_building_audit_statuses') &&
            Schema::hasColumn('public_building_audit_statuses', 'public_building_survey_id')
        ) {
            if (! $this->indexExists('public_building_audit_statuses', 'pb_audit_status_survey_unique')) {
                Schema::table('public_building_audit_statuses', function (Blueprint $table) {
                    $table->unique('public_building_survey_id', 'pb_audit_status_survey_unique');
                });
            }

            if ($this->indexExists('public_building_audit_statuses', 'pb_audit_status_survey_id_index')) {
                Schema::table('public_building_audit_statuses', function (Blueprint $table) {
                    $table->dropIndex('pb_audit_status_survey_id_index');
                });
            }
        }

        if (
            Schema::hasTable('road_facility_audit_statuses') &&
            Schema::hasColumn('road_facility_audit_statuses', 'road_facility_survey_id')
        ) {
            if (! $this->indexExists('road_facility_audit_statuses', 'road_audit_status_survey_unique')) {
                Schema::table('road_facility_audit_statuses', function (Blueprint $table) {
                    $table->unique('road_facility_survey_id', 'road_audit_status_survey_unique');
                });
            }

            if ($this->indexExists('road_facility_audit_statuses', 'road_audit_status_survey_id_index')) {
                Schema::table('road_facility_audit_statuses', function (Blueprint $table) {
                    $table->dropIndex('road_audit_status_survey_id_index');
                });
            }
        }
    }
};
