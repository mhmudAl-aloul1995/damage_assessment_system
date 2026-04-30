<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('public_building_surveys')) {
            Schema::create('public_building_surveys', function (Blueprint $table) {
                $table->id();

                if (!Schema::hasColumn('public_building_surveys', 'objectid')) {
                    $table->unsignedBigInteger('objectid')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'globalid')) {
                    $table->string('globalid', 50);
                }
                if (!Schema::hasColumn('public_building_surveys', 'field_status')) {
                    $table->string('field_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'parcel_no1')) {
                    $table->integer('parcel_no1')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'block_no1')) {
                    $table->integer('block_no1')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'owner_na')) {
                    $table->integer('owner_na')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'assignedto')) {
                    $table->string('assignedto', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'governorate')) {
                    $table->string('governorate', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'municipalitie')) {
                    $table->string('municipalitie', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'neighborhood')) {
                    $table->string('neighborhood', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'weather')) {
                    $table->string('weather', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'security_situation')) {
                    $table->string('security_situation', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'security_info')) {
                    $table->string('security_info', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_name')) {
                    $table->string('building_name', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'street')) {
                    $table->string('street', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'address')) {
                    $table->string('address', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_damage_status')) {
                    $table->text('building_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'date_of_damage')) {
                    $table->text('date_of_damage')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_type')) {
                    $table->text('building_type')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_age')) {
                    $table->string('building_age', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_use')) {
                    $table->string('building_use', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_building_use')) {
                    $table->string('other_building_use', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'floor_nos')) {
                    $table->integer('floor_nos')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'ground_floor_area__m2')) {
                    $table->decimal('ground_floor_area__m2', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'repeated_floor_area__m2')) {
                    $table->decimal('repeated_floor_area__m2', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'sector')) {
                    $table->string('sector', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_sector')) {
                    $table->string('other_sector', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'facility_type')) {
                    $table->text('facility_type')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_health')) {
                    $table->string('other_health', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_education')) {
                    $table->string('other_education', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_culture')) {
                    $table->string('other_culture', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_environmental')) {
                    $table->string('other_environmental', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_governmental')) {
                    $table->string('other_governmental', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_regional')) {
                    $table->string('other_regional', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'no_benef')) {
                    $table->integer('no_benef')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'benef_type')) {
                    $table->text('benef_type')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_benef')) {
                    $table->string('other_benef', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'visit_status')) {
                    $table->string('visit_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_bodies')) {
                    $table->string('is_bodies', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_uxo')) {
                    $table->string('is_uxo', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'occupied_stakeholders')) {
                    $table->string('occupied_stakeholders', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_displaced')) {
                    $table->string('is_displaced', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'number_displaced_families')) {
                    $table->decimal('number_displaced_families', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'land_area__m2')) {
                    $table->decimal('land_area__m2', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'and_ownership')) {
                    $table->string('and_ownership', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_boundaries')) {
                    $table->string('building_boundaries', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'northern_side')) {
                    $table->string('northern_side', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'southern_side')) {
                    $table->string('southern_side', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'eastern_side')) {
                    $table->string('eastern_side', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'western_side')) {
                    $table->string('western_side', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_status')) {
                    $table->string('building_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_status')) {
                    $table->string('other_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_roof_type')) {
                    $table->text('building_roof_type')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'clay_tile_area')) {
                    $table->decimal('clay_tile_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'concrete_area')) {
                    $table->decimal('concrete_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'asbestos_area')) {
                    $table->decimal('asbestos_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'scorite_area')) {
                    $table->decimal('scorite_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_roof')) {
                    $table->string('other_roof', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_roof_area')) {
                    $table->decimal('other_roof_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'no_of_occupied_damaged_units')) {
                    $table->text('no_of_occupied_damaged_units')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'no_of_unoccupied_damaged_units')) {
                    $table->text('no_of_unoccupied_damaged_units')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_damaged_before')) {
                    $table->text('is_damaged_before')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'if_damaged')) {
                    $table->text('if_damaged')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_elevator')) {
                    $table->string('has_elevator', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'elevator_number')) {
                    $table->integer('elevator_number')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'elevator_status')) {
                    $table->string('elevator_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'elevator_box')) {
                    $table->string('elevator_box', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'elevator_motor')) {
                    $table->string('elevator_motor', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_solar')) {
                    $table->string('has_solar', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'solar_damage_status')) {
                    $table->text('solar_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_well')) {
                    $table->string('has_well', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'well_damage_status')) {
                    $table->text('well_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_fence')) {
                    $table->string('has_fence', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'fence_damage_status')) {
                    $table->text('fence_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'fence_length')) {
                    $table->integer('fence_length')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_electric_room')) {
                    $table->string('has_electric_room', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'electric_room_damage_status')) {
                    $table->text('electric_room_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_sewage')) {
                    $table->string('has_sewage', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'sewage_damage_status')) {
                    $table->text('sewage_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_other_service')) {
                    $table->string('has_other_service', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_service_details')) {
                    $table->string('other_service_details', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_services_notes')) {
                    $table->string('building_services_notes', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_basement')) {
                    $table->string('has_basement', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'basement_status')) {
                    $table->string('basement_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'basement_area')) {
                    $table->decimal('basement_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'basement_finishing_type')) {
                    $table->text('basement_finishing_type')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'basement_use')) {
                    $table->string('basement_use', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_basement_use')) {
                    $table->string('other_basement_use', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'ground_floor_use')) {
                    $table->text('ground_floor_use')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'residential_use_area')) {
                    $table->decimal('residential_use_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'work_use_area')) {
                    $table->decimal('work_use_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'canopy_use_area')) {
                    $table->decimal('canopy_use_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_use_area')) {
                    $table->string('other_use_area', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'work_area_use')) {
                    $table->string('work_area_use', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'work_area_finishing')) {
                    $table->string('work_area_finishing', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'comments_recommendations')) {
                    $table->string('comments_recommendations', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'creationdate')) {
                    $table->dateTime('creationdate')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'creator')) {
                    $table->string('creator', 128)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'editdate')) {
                    $table->dateTime('editdate')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'editor')) {
                    $table->string('editor', 128)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_guard_room')) {
                    $table->string('is_guard_room', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_building_occupied')) {
                    $table->string('is_building_occupied', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'occupied_by')) {
                    $table->string('occupied_by', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'units_nos')) {
                    $table->integer('units_nos')->nullable();
                }

                if (!Schema::hasColumn('public_building_surveys', 'raw_payload')) {
                    $table->longText('raw_payload')->nullable();
                }

                $table->timestamps();
            });
        } else {
            Schema::table('public_building_surveys', function (Blueprint $table) {
                if (!Schema::hasColumn('public_building_surveys', 'objectid')) {
                    $table->unsignedBigInteger('objectid')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'globalid')) {
                    $table->string('globalid', 50);
                }
                if (!Schema::hasColumn('public_building_surveys', 'field_status')) {
                    $table->string('field_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'parcel_no1')) {
                    $table->integer('parcel_no1')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'block_no1')) {
                    $table->integer('block_no1')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'owner_na')) {
                    $table->integer('owner_na')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'assignedto')) {
                    $table->string('assignedto', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'governorate')) {
                    $table->string('governorate', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'municipalitie')) {
                    $table->string('municipalitie', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'neighborhood')) {
                    $table->string('neighborhood', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'weather')) {
                    $table->string('weather', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'security_situation')) {
                    $table->string('security_situation', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'security_info')) {
                    $table->string('security_info', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_name')) {
                    $table->string('building_name', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'street')) {
                    $table->string('street', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'address')) {
                    $table->string('address', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_damage_status')) {
                    $table->text('building_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'date_of_damage')) {
                    $table->text('date_of_damage')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_type')) {
                    $table->text('building_type')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_age')) {
                    $table->string('building_age', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_use')) {
                    $table->string('building_use', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_building_use')) {
                    $table->string('other_building_use', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'floor_nos')) {
                    $table->integer('floor_nos')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'ground_floor_area__m2')) {
                    $table->decimal('ground_floor_area__m2', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'repeated_floor_area__m2')) {
                    $table->decimal('repeated_floor_area__m2', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'sector')) {
                    $table->string('sector', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_sector')) {
                    $table->string('other_sector', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'facility_type')) {
                    $table->text('facility_type')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_health')) {
                    $table->string('other_health', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_education')) {
                    $table->string('other_education', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_culture')) {
                    $table->string('other_culture', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_environmental')) {
                    $table->string('other_environmental', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_governmental')) {
                    $table->string('other_governmental', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_regional')) {
                    $table->string('other_regional', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'no_benef')) {
                    $table->integer('no_benef')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'benef_type')) {
                    $table->text('benef_type')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_benef')) {
                    $table->string('other_benef', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'visit_status')) {
                    $table->string('visit_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_bodies')) {
                    $table->string('is_bodies', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_uxo')) {
                    $table->string('is_uxo', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'occupied_stakeholders')) {
                    $table->string('occupied_stakeholders', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_displaced')) {
                    $table->string('is_displaced', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'number_displaced_families')) {
                    $table->decimal('number_displaced_families', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'land_area__m2')) {
                    $table->decimal('land_area__m2', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'and_ownership')) {
                    $table->string('and_ownership', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_boundaries')) {
                    $table->string('building_boundaries', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'northern_side')) {
                    $table->string('northern_side', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'southern_side')) {
                    $table->string('southern_side', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'eastern_side')) {
                    $table->string('eastern_side', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'western_side')) {
                    $table->string('western_side', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_status')) {
                    $table->string('building_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_status')) {
                    $table->string('other_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_roof_type')) {
                    $table->text('building_roof_type')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'clay_tile_area')) {
                    $table->decimal('clay_tile_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'concrete_area')) {
                    $table->decimal('concrete_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'asbestos_area')) {
                    $table->decimal('asbestos_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'scorite_area')) {
                    $table->decimal('scorite_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_roof')) {
                    $table->string('other_roof', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_roof_area')) {
                    $table->decimal('other_roof_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'no_of_occupied_damaged_units')) {
                    $table->text('no_of_occupied_damaged_units')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'no_of_unoccupied_damaged_units')) {
                    $table->text('no_of_unoccupied_damaged_units')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_damaged_before')) {
                    $table->text('is_damaged_before')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'if_damaged')) {
                    $table->text('if_damaged')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_elevator')) {
                    $table->string('has_elevator', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'elevator_number')) {
                    $table->integer('elevator_number')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'elevator_status')) {
                    $table->string('elevator_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'elevator_box')) {
                    $table->string('elevator_box', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'elevator_motor')) {
                    $table->string('elevator_motor', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_solar')) {
                    $table->string('has_solar', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'solar_damage_status')) {
                    $table->text('solar_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_well')) {
                    $table->string('has_well', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'well_damage_status')) {
                    $table->text('well_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_fence')) {
                    $table->string('has_fence', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'fence_damage_status')) {
                    $table->text('fence_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'fence_length')) {
                    $table->integer('fence_length')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_electric_room')) {
                    $table->string('has_electric_room', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'electric_room_damage_status')) {
                    $table->text('electric_room_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_sewage')) {
                    $table->string('has_sewage', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'sewage_damage_status')) {
                    $table->text('sewage_damage_status')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_other_service')) {
                    $table->string('has_other_service', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_service_details')) {
                    $table->string('other_service_details', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'building_services_notes')) {
                    $table->string('building_services_notes', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'has_basement')) {
                    $table->string('has_basement', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'basement_status')) {
                    $table->string('basement_status', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'basement_area')) {
                    $table->decimal('basement_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'basement_finishing_type')) {
                    $table->text('basement_finishing_type')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'basement_use')) {
                    $table->string('basement_use', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_basement_use')) {
                    $table->string('other_basement_use', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'ground_floor_use')) {
                    $table->text('ground_floor_use')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'residential_use_area')) {
                    $table->decimal('residential_use_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'work_use_area')) {
                    $table->decimal('work_use_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'canopy_use_area')) {
                    $table->decimal('canopy_use_area', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'other_use_area')) {
                    $table->string('other_use_area', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'work_area_use')) {
                    $table->string('work_area_use', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'work_area_finishing')) {
                    $table->string('work_area_finishing', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'comments_recommendations')) {
                    $table->string('comments_recommendations', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'creationdate')) {
                    $table->dateTime('creationdate')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'creator')) {
                    $table->string('creator', 128)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'editdate')) {
                    $table->dateTime('editdate')->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'editor')) {
                    $table->string('editor', 128)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_guard_room')) {
                    $table->string('is_guard_room', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'is_building_occupied')) {
                    $table->string('is_building_occupied', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'occupied_by')) {
                    $table->string('occupied_by', 255)->nullable();
                }
                if (!Schema::hasColumn('public_building_surveys', 'units_nos')) {
                    $table->integer('units_nos')->nullable();
                }

                if (!Schema::hasColumn('public_building_surveys', 'raw_payload')) {
                    $table->longText('raw_payload')->nullable();
                }
            });
        }

        Schema::table('public_building_surveys', function (Blueprint $table) {
            try {
                if (Schema::hasColumn('public_building_surveys', 'objectid')) {
                    $table->index('objectid', 'idx_public_building_surveys_objectid');
                }
            } catch (Throwable $e) {}

            try {
                if (Schema::hasColumn('public_building_surveys', 'globalid')) {
                    $table->index('globalid', 'idx_public_building_surveys_globalid');
                }
            } catch (Throwable $e) {}

            try {
                if (Schema::hasColumn('public_building_surveys', 'parentglobalid')) {
                    $table->index('parentglobalid', 'idx_public_building_surveys_parentglobalid');
                }
            } catch (Throwable $e) {}
        });
    }

    public function down(): void
    {
        // Safe rollback: do not drop table automatically.
    }
};