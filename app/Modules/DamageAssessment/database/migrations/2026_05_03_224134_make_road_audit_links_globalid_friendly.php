<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('road_facility_audit_statuses')) {
            $this->dropForeignIfExists('road_facility_audit_statuses', 'road_audit_status_survey_fk');
            $this->dropIndexIfExists('road_facility_audit_statuses', 'road_audit_status_survey_unique');

            if (Schema::hasColumn('road_facility_audit_statuses', 'road_facility_survey_id')) {
                DB::statement('ALTER TABLE road_facility_audit_statuses MODIFY road_facility_survey_id BIGINT UNSIGNED NULL');
            }

            $this->addUniqueIfMissing('road_facility_audit_statuses', 'road_audit_status_globalid_unique', 'globalid');
        }

        if (Schema::hasTable('road_facility_audit_histories') && Schema::hasColumn('road_facility_audit_histories', 'road_facility_survey_id')) {
            $this->dropForeignIfExists('road_facility_audit_histories', 'road_audit_history_survey_fk');
            DB::statement('ALTER TABLE road_facility_audit_histories MODIFY road_facility_survey_id BIGINT UNSIGNED NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('road_facility_audit_statuses')) {
            $this->dropIndexIfExists('road_facility_audit_statuses', 'road_audit_status_globalid_unique');
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$index}");
        }
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        $exists = DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }

    private function addUniqueIfMissing(string $table, string $index, string $column): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();

        if (! $exists) {
            DB::statement("ALTER TABLE {$table} ADD UNIQUE {$index} ({$column})");
        }
    }
};
