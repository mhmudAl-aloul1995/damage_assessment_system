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
        Schema::table('road_facility_surveys', function (Blueprint $table): void {
            if (! Schema::hasColumn('road_facility_surveys', 'shape__length')) {
                $table->double('shape__length')->nullable()->after('raw_payload');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('road_facility_surveys', function (Blueprint $table): void {
            if (Schema::hasColumn('road_facility_surveys', 'shape__length')) {
                $table->dropColumn('shape__length');
            }
        });
    }
};
