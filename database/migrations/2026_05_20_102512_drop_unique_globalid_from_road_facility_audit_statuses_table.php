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
        if (! Schema::hasTable('road_facility_audit_statuses')) {
            return;
        }

        $this->dropIndexIfExists('road_facility_audit_statuses', 'road_audit_status_globalid_unique');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            ! Schema::hasTable('road_facility_audit_statuses') ||
            $this->indexExists('road_facility_audit_statuses', 'road_audit_status_globalid_unique') ||
            $this->hasDuplicateGlobalIds()
        ) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX road_audit_status_globalid_unique ON road_facility_audit_statuses (globalid)');

            return;
        }

        DB::statement('ALTER TABLE road_facility_audit_statuses ADD UNIQUE road_audit_status_globalid_unique (globalid)');
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement("DROP INDEX {$index}");

            return;
        }

        DB::statement("ALTER TABLE {$table} DROP INDEX {$index}");
    }

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

    private function hasDuplicateGlobalIds(): bool
    {
        return DB::table('road_facility_audit_statuses')
            ->select('globalid')
            ->whereNotNull('globalid')
            ->groupBy('globalid')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }
};
