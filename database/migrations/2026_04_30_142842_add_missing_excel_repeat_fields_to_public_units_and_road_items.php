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
        Schema::table('public_building_survey_units', function (Blueprint $table): void {
            foreach ([
                'id_photo',
                'photo_unit_ownership',
                'municipal_permit',
                'other_documents',
                'damge_photo_1',
                'damge_photo_2',
                'damge_photo_3',
            ] as $column) {
                if (! Schema::hasColumn('public_building_survey_units', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });

        Schema::table('road_facility_survey_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('road_facility_survey_items', 'unit_001')) {
                $table->string('unit_001', 255)->nullable();
            }

            if (! Schema::hasColumn('road_facility_survey_items', 'quantity_001')) {
                $table->integer('quantity_001')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_building_survey_units', function (Blueprint $table): void {
            foreach ([
                'id_photo',
                'photo_unit_ownership',
                'municipal_permit',
                'other_documents',
                'damge_photo_1',
                'damge_photo_2',
                'damge_photo_3',
            ] as $column) {
                if (Schema::hasColumn('public_building_survey_units', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('road_facility_survey_items', function (Blueprint $table): void {
            foreach (['unit_001', 'quantity_001'] as $column) {
                if (Schema::hasColumn('road_facility_survey_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
