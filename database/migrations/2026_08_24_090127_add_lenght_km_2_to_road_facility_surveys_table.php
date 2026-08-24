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
        Schema::table('road_facility_surveys', function (Blueprint $table) {
            if (! Schema::hasColumn('road_facility_surveys', 'Lenght_Km_2')) {
                $table->double('Lenght_Km_2')->nullable()->after('shape__length');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('road_facility_surveys', function (Blueprint $table) {
            if (Schema::hasColumn('road_facility_surveys', 'Lenght_Km_2')) {
                $table->dropColumn('Lenght_Km_2');
            }
        });
    }
};
