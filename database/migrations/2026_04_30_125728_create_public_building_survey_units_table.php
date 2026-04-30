<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public_building_survey_units')) {
            Schema::table('public_building_survey_units', function (Blueprint $table): void {
                if (! Schema::hasColumn('public_building_survey_units', 'objectid')) {
                    $table->unsignedBigInteger('objectid')->nullable()->index();
                }

                if (! Schema::hasColumn('public_building_survey_units', 'globalid')) {
                    $table->string('globalid', 50)->nullable()->index();
                }

                if (! Schema::hasColumn('public_building_survey_units', 'parentglobalid')) {
                    $table->string('parentglobalid', 50)->nullable()->index();
                }
            });

            return;
        }

        Schema::create('public_building_survey_units', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('objectid')->nullable();
            $table->string('globalid', 50)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->string('occupied', 255)->nullable();
            $table->string('unit_ownership', 255)->nullable();
            $table->string('other_unit_ownership', 255)->nullable();
            $table->string('unctional_use', 255)->nullable();
            $table->integer('floor_number')->nullable();
            $table->integer('housing_unit_number')->nullable();
            $table->string('the_unit_resident_time_damage', 255)->nullable();
            $table->decimal('damaged_area_m2', 15, 4)->nullable();
            $table->string('rentee_resident_full_name', 255)->nullable();
            $table->string('rentee_mobile_number', 255)->nullable();
            $table->string('work_type', 255)->nullable();
            $table->string('other_work', 255)->nullable();
            $table->string('external_finishing_of_the_unit', 255)->nullable();
            $table->string('other_external_finishing', 255)->nullable();
            $table->string('internal_finishing_of_the_unit', 255)->nullable();
            $table->integer('percentage_of_damaged_furniture')->nullable();
            $table->string('rubble_removal_is_needed', 255)->nullable();
            $table->string('activation_of_uxo_ha_d_material_clearance', 255)->nullable();
            $table->string('inspection_inside_the_housing_unit', 255)->nullable();
            $table->string('is_the_housing_unit_or_living_habitable', 255)->nullable();
            $table->string('general_notes_about_the_unit', 255)->nullable();
            $table->string('attach_one_photo_for_documents', 255)->nullable();
            $table->string('select_document', 255)->nullable();
            $table->decimal('dm1', 15, 4)->nullable();
            $table->decimal('dm2', 15, 4)->nullable();
            $table->decimal('dm3', 15, 4)->nullable();
            $table->decimal('dm4', 15, 4)->nullable();
            $table->decimal('dm5', 15, 4)->nullable();
            $table->decimal('dm6', 15, 4)->nullable();
            $table->decimal('dm7', 15, 4)->nullable();
            $table->decimal('dm8', 15, 4)->nullable();
            $table->decimal('dm9', 15, 4)->nullable();
            $table->decimal('dm10', 15, 4)->nullable();
            $table->decimal('dm11', 15, 4)->nullable();
            $table->decimal('dm12', 15, 4)->nullable();
            $table->decimal('bl1', 15, 4)->nullable();
            $table->decimal('bl2', 15, 4)->nullable();
            $table->decimal('bl3', 15, 4)->nullable();
            $table->decimal('bl4', 15, 4)->nullable();
            $table->decimal('bl5', 15, 4)->nullable();
            $table->decimal('bl6', 15, 4)->nullable();
            $table->decimal('co1', 15, 4)->nullable();
            $table->decimal('co2', 15, 4)->nullable();
            $table->decimal('co3', 15, 4)->nullable();
            $table->decimal('co4', 15, 4)->nullable();
            $table->decimal('co5', 15, 4)->nullable();
            $table->decimal('co6', 15, 4)->nullable();
            $table->decimal('co7', 15, 4)->nullable();
            $table->decimal('co8', 15, 4)->nullable();
            $table->decimal('co9', 15, 4)->nullable();
            $table->decimal('co10', 15, 4)->nullable();
            $table->decimal('fn1', 15, 4)->nullable();
            $table->decimal('fn2', 15, 4)->nullable();
            $table->decimal('fn3', 15, 4)->nullable();
            $table->decimal('fn4', 15, 4)->nullable();
            $table->decimal('fn4_1', 15, 4)->nullable();
            $table->decimal('fn5', 15, 4)->nullable();
            $table->decimal('fn6', 15, 4)->nullable();
            $table->decimal('fn7', 15, 4)->nullable();
            $table->decimal('fn8', 15, 4)->nullable();
            $table->decimal('fn9', 15, 4)->nullable();
            $table->decimal('fn10', 15, 4)->nullable();
            $table->decimal('fn11', 15, 4)->nullable();
            $table->decimal('fn12', 15, 4)->nullable();
            $table->decimal('fn13', 15, 4)->nullable();
            $table->decimal('fn14', 15, 4)->nullable();
            $table->decimal('fn15', 15, 4)->nullable();
            $table->decimal('fn16', 15, 4)->nullable();
            $table->decimal('fn17', 15, 4)->nullable();
            $table->decimal('fn18', 15, 4)->nullable();
            $table->decimal('fn19', 15, 4)->nullable();
            $table->decimal('fn20', 15, 4)->nullable();
            $table->decimal('fn21', 15, 4)->nullable();
            $table->decimal('fn22', 15, 4)->nullable();
            $table->decimal('fn23', 15, 4)->nullable();
            $table->decimal('al1', 15, 4)->nullable();
            $table->decimal('al2', 15, 4)->nullable();
            $table->decimal('al3', 15, 4)->nullable();
            $table->decimal('al4', 15, 4)->nullable();
            $table->decimal('al5', 15, 4)->nullable();
            $table->decimal('al6', 15, 4)->nullable();
            $table->decimal('al7', 15, 4)->nullable();
            $table->decimal('al8', 15, 4)->nullable();
            $table->decimal('al9', 15, 4)->nullable();
            $table->decimal('al10', 15, 4)->nullable();
            $table->decimal('al11', 15, 4)->nullable();
            $table->decimal('al12', 15, 4)->nullable();
            $table->decimal('wd1', 15, 4)->nullable();
            $table->decimal('wd2', 15, 4)->nullable();
            $table->decimal('wd3', 15, 4)->nullable();
            $table->decimal('wd4', 15, 4)->nullable();
            $table->decimal('wd5', 15, 4)->nullable();
            $table->decimal('wd6', 15, 4)->nullable();
            $table->decimal('mt1', 15, 4)->nullable();
            $table->decimal('mt2', 15, 4)->nullable();
            $table->decimal('mt3', 15, 4)->nullable();
            $table->decimal('mt4', 15, 4)->nullable();
            $table->decimal('mt5', 15, 4)->nullable();
            $table->decimal('mt6', 15, 4)->nullable();
            $table->decimal('mt7', 15, 4)->nullable();
            $table->decimal('mt8', 15, 4)->nullable();
            $table->decimal('mt9', 15, 4)->nullable();
            $table->decimal('mt10', 15, 4)->nullable();
            $table->decimal('mt11', 15, 4)->nullable();
            $table->decimal('mt12', 15, 4)->nullable();
            $table->decimal('mt13', 15, 4)->nullable();
            $table->decimal('mt14', 15, 4)->nullable();
            $table->decimal('mt15', 15, 4)->nullable();
            $table->decimal('mt16', 15, 4)->nullable();
            $table->decimal('mt17', 15, 4)->nullable();
            $table->decimal('mt18', 15, 4)->nullable();
            $table->decimal('cm1', 15, 4)->nullable();
            $table->decimal('cm2', 15, 4)->nullable();
            $table->decimal('cm3', 15, 4)->nullable();
            $table->decimal('cm4', 15, 4)->nullable();
            $table->decimal('cm5', 15, 4)->nullable();
            $table->decimal('cm6', 15, 4)->nullable();
            $table->decimal('cm7', 15, 4)->nullable();
            $table->decimal('cm8', 15, 4)->nullable();
            $table->decimal('pm1', 15, 4)->nullable();
            $table->decimal('pm2', 15, 4)->nullable();
            $table->decimal('pm3', 15, 4)->nullable();
            $table->decimal('pm4', 15, 4)->nullable();
            $table->decimal('pm5', 15, 4)->nullable();
            $table->decimal('pm6', 15, 4)->nullable();
            $table->decimal('pm7', 15, 4)->nullable();
            $table->decimal('pm8', 15, 4)->nullable();
            $table->decimal('pm9', 15, 4)->nullable();
            $table->decimal('pm10', 15, 4)->nullable();
            $table->decimal('pm11', 15, 4)->nullable();
            $table->decimal('pm12', 15, 4)->nullable();
            $table->decimal('pm13', 15, 4)->nullable();
            $table->decimal('pm14', 15, 4)->nullable();
            $table->decimal('pm15', 15, 4)->nullable();
            $table->decimal('pm16', 15, 4)->nullable();
            $table->decimal('pm17', 15, 4)->nullable();
            $table->decimal('pm18', 15, 4)->nullable();
            $table->decimal('pm19', 15, 4)->nullable();
            $table->decimal('pm20', 15, 4)->nullable();
            $table->decimal('pm21', 15, 4)->nullable();
            $table->decimal('pm22', 15, 4)->nullable();
            $table->decimal('pm23', 15, 4)->nullable();
            $table->decimal('pm24', 15, 4)->nullable();
            $table->decimal('pm25', 15, 4)->nullable();
            $table->decimal('pm26', 15, 4)->nullable();
            $table->decimal('pm27', 15, 4)->nullable();
            $table->decimal('pm28', 15, 4)->nullable();
            $table->decimal('pm29', 15, 4)->nullable();
            $table->decimal('pm30', 15, 4)->nullable();
            $table->decimal('pm31', 15, 4)->nullable();
            $table->decimal('el1', 15, 4)->nullable();
            $table->decimal('el2', 15, 4)->nullable();
            $table->decimal('el3', 15, 4)->nullable();
            $table->decimal('el4', 15, 4)->nullable();
            $table->decimal('el5', 15, 4)->nullable();
            $table->decimal('el6', 15, 4)->nullable();
            $table->decimal('el7', 15, 4)->nullable();
            $table->decimal('el8', 15, 4)->nullable();
            $table->decimal('el9', 15, 4)->nullable();
            $table->decimal('el10', 15, 4)->nullable();
            $table->decimal('el11', 15, 4)->nullable();
            $table->decimal('el12', 15, 4)->nullable();
            $table->decimal('el13', 15, 4)->nullable();
            $table->decimal('el14', 15, 4)->nullable();
            $table->decimal('el15', 15, 4)->nullable();
            $table->decimal('el16', 15, 4)->nullable();
            $table->decimal('el17', 15, 4)->nullable();
            $table->decimal('el18', 15, 4)->nullable();
            $table->decimal('el19', 15, 4)->nullable();
            $table->decimal('el20', 15, 4)->nullable();
            $table->decimal('el21', 15, 4)->nullable();
            $table->decimal('el22', 15, 4)->nullable();
            $table->decimal('el23', 15, 4)->nullable();
            $table->decimal('el24', 15, 4)->nullable();
            $table->decimal('el25', 15, 4)->nullable();
            $table->integer('pv1')->nullable();
            $table->integer('pv2')->nullable();
            $table->integer('pv3')->nullable();
            $table->integer('pv4')->nullable();
            $table->integer('pv5')->nullable();
            $table->decimal('pv6', 15, 4)->nullable();
            $table->string('item1', 255)->nullable();
            $table->decimal('quant1', 15, 4)->nullable();
            $table->string('item2', 255)->nullable();
            $table->decimal('quant2', 15, 4)->nullable();
            $table->string('item3', 255)->nullable();
            $table->decimal('quant3', 15, 4)->nullable();
            $table->string('item4', 255)->nullable();
            $table->decimal('quant4', 15, 4)->nullable();
            $table->string('item5', 255)->nullable();
            $table->decimal('quant5', 15, 4)->nullable();
            $table->string('final_comments', 255)->nullable();
            $table->string('parentglobalid', 50)->nullable();
            $table->dateTime('creationdate')->nullable();
            $table->string('creator', 255)->nullable();
            $table->dateTime('editdate')->nullable();
            $table->string('editor', 255)->nullable();
            $table->string('has_fire', 255)->nullable();
            $table->string('fire_extent', 255)->nullable();
            $table->string('fire_severity', 255)->nullable();
            $table->string('fire_locations', 255)->nullable();
            $table->integer('fire_rooms_count')->nullable();
            $table->decimal('fire_area', 15, 4)->nullable();
            $table->string('unit_stripping', 255)->nullable();
            $table->string('unit_stripping_details', 255)->nullable();
            $table->decimal('stripping_area', 15, 4)->nullable();
            $table->string('stripping_locations', 255)->nullable();
            $table->string('unit_support_needed', 255)->nullable();
            $table->string('staircase_status', 255)->nullable();
            $table->decimal('staircase_widt', 15, 4)->nullable();
            $table->string('has_parking', 255)->nullable();
            $table->string('parking_status', 255)->nullable();
            $table->decimal('garage_area', 15, 4)->nullable();
            $table->string('garage_type', 255)->nullable();

            // 🔥 Full payload
            $table->longText('raw_payload')->nullable();

            $table->timestamps();

            // 🔥 Indexes
            if (Schema::hasColumn('public_building_survey_units', 'objectid')) {
                $table->index('objectid');
            }

            if (Schema::hasColumn('public_building_survey_units', 'globalid')) {
                $table->index('globalid');
            }

            if (Schema::hasColumn('public_building_survey_units', 'parentglobalid')) {
                $table->index('parentglobalid');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_building_survey_units');
    }
};
