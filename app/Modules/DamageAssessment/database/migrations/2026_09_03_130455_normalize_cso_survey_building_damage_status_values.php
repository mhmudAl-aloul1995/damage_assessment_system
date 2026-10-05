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
        if (! Schema::hasTable('cso_surveys') || ! Schema::hasColumn('cso_surveys', 'building_damage_status')) {
            return;
        }

        DB::table('cso_surveys')
            ->whereIn(DB::raw('LOWER(TRIM(building_damage_status))'), [
                'total',
                'totally',
                'total_damage',
                'totally_damaged',
                'totally damaged',
                'fully_damaged',
                'fully damaged',
            ])
            ->update(['building_damage_status' => '1']);

        DB::table('cso_surveys')
            ->whereIn(DB::raw('LOWER(TRIM(building_damage_status))'), [
                'partial',
                'partial_damage',
                'partially_damaged',
                'partially damaged',
            ])
            ->update(['building_damage_status' => '2']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
