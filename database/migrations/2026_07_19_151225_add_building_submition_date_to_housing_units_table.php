<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('housing_units', function (Blueprint $table) {
            if (! Schema::hasColumn('housing_units', 'building_submition_date')) {
                $table->text('building_submition_date')->nullable()->after('building_submit_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('housing_units', function (Blueprint $table) {
            if (Schema::hasColumn('housing_units', 'building_submition_date')) {
                $table->dropColumn('building_submition_date');
            }
        });
    }
};
