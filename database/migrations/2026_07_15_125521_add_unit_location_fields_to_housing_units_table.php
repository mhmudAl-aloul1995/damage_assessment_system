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
        Schema::table('housing_units', function (Blueprint $table) {
            if (! Schema::hasColumn('housing_units', 'unit_governorate')) {
                $table->string('unit_governorate')->nullable()->after('building_submit_date');
            }

            if (! Schema::hasColumn('housing_units', 'unit_municipalitie')) {
                $table->string('unit_municipalitie')->nullable()->after('unit_governorate');
            }

            if (! Schema::hasColumn('housing_units', 'unit_neighborhood')) {
                $table->string('unit_neighborhood')->nullable()->after('unit_municipalitie');
            }

            if (! Schema::hasColumn('housing_units', 'unit_building_name')) {
                $table->string('unit_building_name')->nullable()->after('unit_neighborhood');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('housing_units', function (Blueprint $table) {
            foreach (['unit_building_name', 'unit_neighborhood', 'unit_municipalitie', 'unit_governorate'] as $column) {
                if (Schema::hasColumn('housing_units', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
