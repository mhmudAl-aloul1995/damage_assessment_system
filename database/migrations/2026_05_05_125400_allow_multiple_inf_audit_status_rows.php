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
        $this->dropIndexIfExists('public_building_audit_statuses', 'pb_audit_status_survey_unique');
        $this->dropIndexIfExists('road_facility_audit_statuses', 'road_audit_status_globalid_unique');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->addUniqueIfMissing('public_building_audit_statuses', 'pb_audit_status_survey_unique', 'public_building_survey_id');
        $this->addUniqueIfMissing('road_facility_audit_statuses', 'road_audit_status_globalid_unique', 'globalid');
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, fn ($schema) => $schema->dropIndex($index));
    }

    private function addUniqueIfMissing(string $table, string $index, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, fn ($schema) => $schema->unique($column, $index));
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $sqliteIndex) {
                if (($sqliteIndex->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
