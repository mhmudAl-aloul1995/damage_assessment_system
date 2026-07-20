<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('housing_units', 'building_field_status')) {
            Schema::table('housing_units', function (Blueprint $table): void {
                $column = $table->string('building_field_status')->nullable();

                if (Schema::hasColumn('housing_units', 'building_submit_date')) {
                    $column->after('building_submit_date');
                }
            });
        }

        DB::table('housing_units')
            ->whereExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('buildings')
                    ->whereColumn('buildings.globalid', 'housing_units.parentglobalid')
                    ->whereNotNull('buildings.field_status');
            })
            ->update([
                'building_field_status' => DB::raw('(select buildings.field_status from buildings where buildings.globalid = housing_units.parentglobalid limit 1)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('housing_units', 'building_field_status')) {
            Schema::table('housing_units', function (Blueprint $table): void {
                $table->dropColumn('building_field_status');
            });
        }
    }
};
