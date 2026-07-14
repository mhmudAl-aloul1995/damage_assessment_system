<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->createIndexIfMissing(
            'edit_assessments',
            'edit_assessments_audited_latest_field_idx',
            'CREATE INDEX edit_assessments_audited_latest_field_idx ON edit_assessments (type, global_id(191), field_name, id)'
        );

        $this->createIndexIfMissing(
            'edit_assessments',
            'edit_assessments_audited_status_idx',
            'CREATE INDEX edit_assessments_audited_status_idx ON edit_assessments (type, field_name, global_id(191), id)'
        );

        $this->createIndexIfMissing(
            'edit_assessments',
            'edit_assessments_audited_meta_idx',
            'CREATE INDEX edit_assessments_audited_meta_idx ON edit_assessments (type, global_id(191), id)'
        );

        $this->createIndexIfMissing(
            'building_statuses',
            'building_statuses_export_lookup_idx',
            'CREATE INDEX building_statuses_export_lookup_idx ON building_statuses (building_id, status_id)'
        );

        $this->createIndexIfMissing(
            'housing_statuses',
            'housing_statuses_export_lookup_idx',
            'CREATE INDEX housing_statuses_export_lookup_idx ON housing_statuses (housing_id, status_id)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropIndexIfExists('housing_statuses', 'housing_statuses_export_lookup_idx');
        $this->dropIndexIfExists('building_statuses', 'building_statuses_export_lookup_idx');
        $this->dropIndexIfExists('edit_assessments', 'edit_assessments_audited_meta_idx');
        $this->dropIndexIfExists('edit_assessments', 'edit_assessments_audited_status_idx');
        $this->dropIndexIfExists('edit_assessments', 'edit_assessments_audited_latest_field_idx');
    }

    private function createIndexIfMissing(string $table, string $index, string $sql): void
    {
        if (! $this->indexExists($table, $index)) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            DB::statement("DROP INDEX {$index} ON {$table}");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
