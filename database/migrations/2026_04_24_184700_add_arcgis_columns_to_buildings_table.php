<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {

            if (!Schema::hasColumn('buildings', 'objectid')) {
                $table->unsignedBigInteger('objectid')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('buildings', 'arcgis_hash')) {
                $table->string('arcgis_hash', 64)
                    ->nullable()
                    ->index()
                    ->after('objectid');
            }

            if (!Schema::hasColumn('buildings', 'arcgis_synced_at')) {
                $table->timestamp('arcgis_synced_at')
                    ->nullable()
                    ->after('arcgis_hash');
            }

            if (!Schema::hasColumn('buildings', 'all_data')) {
                $table->longText('all_data')
                    ->nullable()
                    ->after('arcgis_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {

            if (Schema::hasColumn('buildings', 'all_data')) {
                $table->dropColumn('all_data');
            }

            if (Schema::hasColumn('buildings', 'arcgis_synced_at')) {
                $table->dropColumn('arcgis_synced_at');
            }

            if (Schema::hasColumn('buildings', 'arcgis_hash')) {
                $table->dropIndex(['arcgis_hash']);
                $table->dropColumn('arcgis_hash');
            }

            // احذف objectid فقط إذا أضفته أنت الآن
            // إذا كان موجود أصلًا تجاهل هذا الجزء
            /*
            if (Schema::hasColumn('buildings', 'objectid')) {
                $table->dropUnique(['objectid']);
                $table->dropColumn('objectid');
            }
            */
        });
    }
};