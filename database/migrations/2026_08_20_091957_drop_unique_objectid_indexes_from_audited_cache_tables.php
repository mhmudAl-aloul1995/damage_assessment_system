<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['audited_buildings', 'audited_housing_units'] as $tableName) {
            $this->dropUniqueObjectIdIndexes($tableName);
            $this->ensureObjectIdIndex($tableName);
        }
    }

    public function down(): void
    {
        //
    }

    private function dropUniqueObjectIdIndexes(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $indexes = DB::select(
            'SHOW INDEX FROM `'.$tableName."` WHERE Non_unique = 0 AND Column_name = 'objectid' AND Key_name <> 'PRIMARY'"
        );

        foreach ($indexes as $index) {
            DB::statement('ALTER TABLE `'.$tableName.'` DROP INDEX `'.$index->Key_name.'`');
        }
    }

    private function ensureObjectIdIndex(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS '.$tableName.'_objectid_non_unique_index ON `'.$tableName.'` (`objectid`)');
    }
};
