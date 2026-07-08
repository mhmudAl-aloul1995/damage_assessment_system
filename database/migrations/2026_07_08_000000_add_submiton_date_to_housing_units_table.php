<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('housing_units', function (Blueprint $table) {
            if (! Schema::hasColumn('housing_units', 'submiton_date')) {
                $table->dateTime('submiton_date')->nullable()->after('building_submit_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('housing_units', function (Blueprint $table) {
            if (Schema::hasColumn('housing_units', 'submiton_date')) {
                $table->dropColumn('submiton_date');
            }
        });
    }
};
