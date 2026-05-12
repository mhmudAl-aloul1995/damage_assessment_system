<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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

        DB::statement('CREATE INDEX buildings_globalid_objectid_export_index ON buildings (globalid(191), objectid)');
        DB::statement('CREATE INDEX housing_units_parent_objectid_export_index ON housing_units (parentglobalid(191), objectid)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('DROP INDEX buildings_globalid_objectid_export_index ON buildings');
        DB::statement('DROP INDEX housing_units_parent_objectid_export_index ON housing_units');
    }
};
