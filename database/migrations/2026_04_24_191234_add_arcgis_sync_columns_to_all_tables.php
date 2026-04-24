<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'buildings',
        'housing_units',
        'public_buildings',
        'road_facilities',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {

            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {

                if (!Schema::hasColumn($tableName, 'arcgis_hash')) {
                    $table->string('arcgis_hash', 64)
                        ->nullable()
                        ->index()
                        ->after('objectid');
                }

                if (!Schema::hasColumn($tableName, 'arcgis_synced_at')) {
                    $table->timestamp('arcgis_synced_at')
                        ->nullable()
                        ->after('arcgis_hash');
                }

            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {

            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {

                if (Schema::hasColumn($tableName, 'arcgis_synced_at')) {
                    $table->dropColumn('arcgis_synced_at');
                }

                if (Schema::hasColumn($tableName, 'arcgis_hash')) {
                    $table->dropIndex([$tableName . '_arcgis_hash_index']);
                    $table->dropColumn('arcgis_hash');
                }

            });
        }
    }
};