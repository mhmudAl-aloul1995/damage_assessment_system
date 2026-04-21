<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_building_surveys', function (Blueprint $table): void {
            $table->id();
            $table->longText('location')->nullable();
            $table->string('field_status')->nullable();
            $table->integer('parcel_no1')->nullable();
            $table->integer('block_no1')->nullable();
            $table->integer('owner_na')->nullable();
            $table->string('assigned_to')->nullable();
            $table->integer('objectid')->nullable()->unique();
            $table->string('governorate')->nullable();
            $table->string('municipalitie')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('weather')->nullable();
            $table->string('security_situation')->nullable();
            $table->text('security_info')->nullable();
            $table->string('building_name')->nullable();
            $table->string('street')->nullable();
            $table->text('address')->nullable();
            $table->string('building_damage_status')->nullable();
            $table->date('date_of_damage')->nullable();
            $table->string('building_type')->nullable();
            $table->string('building_age')->nullable();
            $table->string('building_use')->nullable();
            $table->text('other_building_use')->nullable();
            $table->integer('floor_nos')->nullable();
            $table->integer('units_nos')->nullable();
            $table->decimal('ground_floor_area_m2', 12, 2)->nullable();
            $table->decimal('repeated_floor_area_m2', 12, 2)->nullable();
            $table->string('sector')->nullable();
            $table->text('other_sector')->nullable();
            $table->string('facility_type')->nullable();
            $table->text('other_health')->nullable();
            $table->text('other_education')->nullable();
            $table->text('other_culture')->nullable();
            $table->text('other_environmental')->nullable();
            $table->text('other_governmental')->nullable();
            $table->text('other_regional')->nullable();
            $table->integer('no_benef')->nullable();
            $table->json('benef_type')->nullable();
            $table->text('other_benef')->nullable();
            $table->string('visit_status')->nullable();
            $table->string('is_bodies')->nullable();
            $table->string('is_uxo')->nullable();
            $table->string('is_building_occupied')->nullable();
            $table->string('occupied_by')->nullable();
            $table->decimal('number_displaced_families', 12, 2)->nullable();
            $table->decimal('land_area_m2', 12, 2)->nullable();
            $table->string('and_ownership')->nullable();
            $table->text('northern_side')->nullable();
            $table->text('southern_side')->nullable();
            $table->text('eastern_side')->nullable();
            $table->text('western_side')->nullable();
            $table->string('building_status')->nullable();
            $table->text('other_status')->nullable();
            $table->json('building_roof_type')->nullable();
            $table->decimal('clay_tile_area', 12, 2)->nullable();
            $table->decimal('concrete_area', 12, 2)->nullable();
            $table->decimal('asbestos_area', 12, 2)->nullable();
            $table->decimal('scorite_area', 12, 2)->nullable();
            $table->text('other_roof')->nullable();
            $table->decimal('other_roof_area', 12, 2)->nullable();
            $table->integer('no_of_occupied_damaged_units')->nullable();
            $table->integer('no_of_unoccupied_damaged_units')->nullable();
            $table->string('is_damaged_before')->nullable();
            $table->text('if_damaged')->nullable();
            $table->string('has_elevator')->nullable();
            $table->integer('elevator_number')->nullable();
            $table->string('elevator_status')->nullable();
            $table->string('elevator_box')->nullable();
            $table->string('elevator_motor')->nullable();
            $table->string('has_solar')->nullable();
            $table->string('solar_damage_status')->nullable();
            $table->string('has_well')->nullable();
            $table->string('well_damage_status')->nullable();
            $table->string('has_fence')->nullable();
            $table->string('fence_damage_status')->nullable();
            $table->integer('fence_length')->nullable();
            $table->string('has_electric_room')->nullable();
            $table->string('electric_room_damage_status')->nullable();
            $table->string('has_sewage')->nullable();
            $table->string('sewage_damage_status')->nullable();
            $table->string('has_other_service')->nullable();
            $table->text('other_service_details')->nullable();
            $table->text('building_services_notes')->nullable();
            $table->string('has_basement')->nullable();
            $table->string('basement_status')->nullable();
            $table->decimal('basement_area', 12, 2)->nullable();
            $table->string('basement_finishing_type')->nullable();
            $table->string('basement_use')->nullable();
            $table->text('other_basement_use')->nullable();
            $table->json('ground_floor_use')->nullable();
            $table->decimal('residential_use_area', 12, 2)->nullable();
            $table->decimal('work_use_area', 12, 2)->nullable();
            $table->decimal('canopy_use_area', 12, 2)->nullable();
            $table->text('other_use_area')->nullable();
            $table->string('work_area_use')->nullable();
            $table->string('work_area_finishing')->nullable();
            $table->string('is_guard_room')->nullable();
            $table->text('comments_recommendations')->nullable();
            $table->text('building_image_1')->nullable();
            $table->text('building_image_2')->nullable();
            $table->text('building_image_3')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('public_building_survey_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_building_survey_id')
                ->constrained('public_building_surveys')
                ->cascadeOnDelete();
            $table->unsignedInteger('repeat_index')->default(0);

            $table->string('unit_name')->nullable();
            $table->string('occupied')->nullable();
            $table->string('unit_ownership')->nullable();
            $table->text('other_unit_ownership')->nullable();
            $table->string('unctional_use')->nullable();
            $table->integer('floor_number')->nullable();
            $table->integer('housing_unit_number')->nullable();
            $table->string('the_unit_resident_time_damage')->nullable();
            $table->decimal('damaged_area_m2', 12, 2)->nullable();
            $table->string('rentee_mobile_number')->nullable();
            $table->string('work_type')->nullable();
            $table->text('other_work')->nullable();
            $table->string('external_finishing_of_the_unit')->nullable();
            $table->text('other_external_finishing')->nullable();
            $table->string('internal_finishing_of_the_unit')->nullable();
            $table->integer('percentage_of_damaged_furniture')->nullable();
            $table->string('rubble_removal_is_needed')->nullable();
            $table->string('activation_of_uxo_ha_d_material_clearance')->nullable();
            $table->string('inspection_inside_the_housing_unit')->nullable();
            $table->string('is_the_housing_unit_or_living_habitable')->nullable();
            $table->json('select_document')->nullable();
            $table->text('id_photo')->nullable();
            $table->text('photo_unit_ownership')->nullable();
            $table->text('municipal_permit')->nullable();
            $table->text('other_documents')->nullable();

            foreach ([
                'dm1', 'dm2', 'dm3', 'dm4', 'dm5', 'dm6', 'dm7', 'dm8', 'dm9', 'dm10', 'dm11', 'dm12',
                'bl1', 'bl2', 'bl3', 'bl4', 'bl5', 'bl6',
                'co1', 'co2', 'co3', 'co4', 'co5', 'co6', 'co7', 'co8', 'co9', 'co10',
                'fn1', 'fn2', 'fn3', 'fn4', 'fn4_1', 'fn5', 'fn6', 'fn7', 'fn8', 'fn9', 'fn10', 'fn11', 'fn12', 'fn13', 'fn14', 'fn15', 'fn16', 'fn17', 'fn18', 'fn19', 'fn20', 'fn21', 'fn22', 'fn23',
                'al1', 'al2', 'al3', 'al4', 'al5', 'al6', 'al7', 'al8', 'al9', 'al10', 'al11', 'al12',
                'wd1', 'wd2', 'wd3', 'wd4', 'wd5', 'wd6',
                'mt1', 'mt2', 'mt3', 'mt4', 'mt5', 'mt6', 'mt7', 'mt8', 'mt9', 'mt10', 'mt11', 'mt12', 'mt13', 'mt14', 'mt15', 'mt16', 'mt17', 'mt18',
                'cm1', 'cm2', 'cm3', 'cm4', 'cm5', 'cm6', 'cm7', 'cm8',
                'pm1', 'pm2', 'pm3', 'pm4', 'pm5', 'pm6', 'pm7', 'pm8', 'pm9', 'pm10', 'pm11', 'pm12', 'pm13', 'pm14', 'pm15', 'pm16', 'pm17', 'pm18', 'pm19', 'pm20', 'pm21', 'pm22', 'pm23', 'pm24', 'pm25', 'pm26', 'pm27', 'pm28', 'pm29', 'pm30', 'pm31',
                'el1', 'el2', 'el3', 'el4', 'el5', 'el6', 'el7', 'el8', 'el9', 'el10', 'el11', 'el12', 'el13', 'el14', 'el15', 'el16', 'el17', 'el18', 'el19', 'el20', 'el21', 'el22', 'el23', 'el24', 'el25',
                'quant1', 'quant2', 'quant3', 'quant4', 'quant5',
                'pv6',
            ] as $decimalColumn) {
                $table->decimal($decimalColumn, 12, 2)->nullable();
            }

            foreach (['pv1', 'pv2', 'pv3', 'pv4', 'pv5'] as $integerColumn) {
                $table->integer($integerColumn)->nullable();
            }

            foreach (['item1', 'item2', 'item3', 'item4', 'item5'] as $textColumn) {
                $table->text($textColumn)->nullable();
            }

            $table->text('damge_photo_1')->nullable();
            $table->text('damge_photo_2')->nullable();
            $table->text('damge_photo_3')->nullable();
            $table->text('final_comments')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['public_building_survey_id', 'repeat_index'], 'pbs_units_survey_repeat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_building_survey_units');
        Schema::dropIfExists('public_building_surveys');
    }
};
