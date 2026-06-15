<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('arcgis_attachment_backups', function (Blueprint $table) {
            $table->string('housing_unit_globalid')->nullable()->after('building_objectid')->index();
            $table->unsignedBigInteger('housing_unit_objectid')->nullable()->after('housing_unit_globalid')->index();

            $table->index(['housing_unit_globalid', 'attachment_id'], 'arcgis_backup_housing_attachment_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arcgis_attachment_backups', function (Blueprint $table) {
            $table->dropIndex('arcgis_backup_housing_attachment_idx');
            $table->dropIndex(['housing_unit_globalid']);
            $table->dropIndex(['housing_unit_objectid']);
            $table->dropColumn(['housing_unit_globalid', 'housing_unit_objectid']);
        });
    }
};
