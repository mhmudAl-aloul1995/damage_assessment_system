<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('housing_units', function (Blueprint $table) {
            if (! Schema::hasColumn('housing_units', 'building_submit_date')) {
                $table->text('building_submit_date')->nullable()->after('parentglobalid');
            }

            if (! Schema::hasColumn('housing_units', 'municipalitie')) {
                $table->text('municipalitie')->nullable()->after('governorate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('housing_units', function (Blueprint $table) {
            if (Schema::hasColumn('housing_units', 'building_submit_date')) {
                $table->dropColumn('building_submit_date');
            }

            if (Schema::hasColumn('housing_units', 'municipalitie')) {
                $table->dropColumn('municipalitie');
            }
        });
    }
};
