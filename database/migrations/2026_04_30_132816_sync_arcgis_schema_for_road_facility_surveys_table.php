<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('road_facility_surveys')) {
            Schema::create('road_facility_surveys', function (Blueprint $table) {
                $table->id();

                if (! Schema::hasColumn('road_facility_surveys', 'objectid')) {
                    $table->unsignedBigInteger('objectid')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'globalid')) {
                    $table->string('globalid', 50);
                }
                if (! Schema::hasColumn('road_facility_surveys', 'field_status')) {
                    $table->string('field_status', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'governorate')) {
                    $table->string('governorate', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'municipalitie')) {
                    $table->string('municipalitie', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'neighborhood')) {
                    $table->string('neighborhood', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'assignedto')) {
                    $table->string('assignedto', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'groupnumber')) {
                    $table->integer('groupnumber')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'zone_code')) {
                    $table->string('zone_code', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'audit')) {
                    $table->string('audit', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'audit_low')) {
                    $table->string('audit_low', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'weather')) {
                    $table->string('weather', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'security_situation')) {
                    $table->string('security_situation', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'security_info')) {
                    $table->string('security_info', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'str_name')) {
                    $table->string('str_name', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'str_no')) {
                    $table->integer('str_no')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'closest_facility')) {
                    $table->string('closest_facility', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'local_authority_name')) {
                    $table->string('local_authority_name', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'local_authority_representative_name')) {
                    $table->string('local_authority_representative_name', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'representative_title')) {
                    $table->string('representative_title', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'rep_mobile_no')) {
                    $table->string('rep_mobile_no', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'road_damage_level')) {
                    $table->text('road_damage_level')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'road_access')) {
                    $table->string('road_access', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'blockage_reason')) {
                    $table->text('blockage_reason')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'potholes_exist')) {
                    $table->string('potholes_exist', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'potholes_count')) {
                    $table->integer('potholes_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'potholes_volume_m3')) {
                    $table->decimal('potholes_volume_m3', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'damaged_road_width_m')) {
                    $table->text('damaged_road_width_m')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'lane_count')) {
                    $table->string('lane_count', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'road_type_note')) {
                    $table->text('road_type_note')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'road_type')) {
                    $table->text('road_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'other_read_type')) {
                    $table->text('other_read_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'asphalt')) {
                    $table->string('asphalt', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'no_layers')) {
                    $table->integer('no_layers')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'thickness_cm')) {
                    $table->decimal('thickness_cm', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'area_m2')) {
                    $table->decimal('area_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'basecoarse')) {
                    $table->string('basecoarse', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'no_layers_001')) {
                    $table->integer('no_layers_001')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'thickness_cm_001')) {
                    $table->decimal('thickness_cm_001', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'area_m2_001')) {
                    $table->decimal('area_m2_001', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'concrete_m3')) {
                    $table->decimal('concrete_m3', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'sidewalk_interlock_m2')) {
                    $table->decimal('sidewalk_interlock_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'sidewalk_damage_type')) {
                    $table->text('sidewalk_damage_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'street_interlock_m2')) {
                    $table->decimal('street_interlock_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'curbstone_m')) {
                    $table->decimal('curbstone_m', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'unpaved_road_m2')) {
                    $table->decimal('unpaved_road_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'lighting_electrical_network')) {
                    $table->decimal('lighting_electrical_network', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'lighting_poles')) {
                    $table->string('lighting_poles', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'pole_type')) {
                    $table->text('pole_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'no_steel_pole')) {
                    $table->integer('no_steel_pole')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'no_wooden_pole')) {
                    $table->integer('no_wooden_pole')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'other_pole')) {
                    $table->string('other_pole', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'no_other_pole')) {
                    $table->integer('no_other_pole')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'lanterns_damaged')) {
                    $table->text('lanterns_damaged')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'lanterns_count')) {
                    $table->integer('lanterns_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'electric_poles_damaged')) {
                    $table->text('electric_poles_damaged')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'pole_voltage_level')) {
                    $table->string('pole_voltage_level', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'pole_material')) {
                    $table->string('pole_material', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'electric_poles_count')) {
                    $table->integer('electric_poles_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'transformers_damaged')) {
                    $table->text('transformers_damaged')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'transformers_count')) {
                    $table->integer('transformers_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'cabinets_exist')) {
                    $table->string('cabinets_exist', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'cabinets_count')) {
                    $table->integer('cabinets_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'aerial_cables_exist')) {
                    $table->string('aerial_cables_exist', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'cable_voltage_level')) {
                    $table->string('cable_voltage_level', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'aerial_cables_length')) {
                    $table->decimal('aerial_cables_length', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'stormwater_inlets_count')) {
                    $table->integer('stormwater_inlets_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'manhole_covers_missing')) {
                    $table->integer('manhole_covers_missing')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'surface_channels_length')) {
                    $table->decimal('surface_channels_length', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'water_ponding')) {
                    $table->string('water_ponding', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'traffic_signs_type')) {
                    $table->text('traffic_signs_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'traffic_signs_count')) {
                    $table->integer('traffic_signs_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'demolition_scope')) {
                    $table->text('demolition_scope')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'demolish_asphalt_m2')) {
                    $table->decimal('demolish_asphalt_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'demolish_base_m2')) {
                    $table->decimal('demolish_base_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'demolish_subbase_m2')) {
                    $table->decimal('demolish_subbase_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'obstacle_exist')) {
                    $table->string('obstacle_exist', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'obstacle_type')) {
                    $table->text('obstacle_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'obstacle_volume_m3')) {
                    $table->decimal('obstacle_volume_m3', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'handrails_damaged_mr')) {
                    $table->text('handrails_damaged_mr')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'road_painting_m2')) {
                    $table->decimal('road_painting_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'curbstone_painting_mr')) {
                    $table->decimal('curbstone_painting_mr', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'handrails_painting_mr')) {
                    $table->decimal('handrails_painting_mr', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'other_note')) {
                    $table->string('other_note', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'buried_bodies')) {
                    $table->string('buried_bodies', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'buried_bodies_est')) {
                    $table->integer('buried_bodies_est')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'uxo_present')) {
                    $table->string('uxo_present', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'final_comments')) {
                    $table->string('final_comments', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'creationdate')) {
                    $table->dateTime('creationdate')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'creator')) {
                    $table->string('creator', 128)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'editdate')) {
                    $table->dateTime('editdate')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'editor')) {
                    $table->string('editor', 128)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'sidewalk_basecourse_m2')) {
                    $table->decimal('sidewalk_basecourse_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'curbstone_damaged_m')) {
                    $table->text('curbstone_damaged_m')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'curbstone_repair_m')) {
                    $table->decimal('curbstone_repair_m', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'curbstone_painting_m')) {
                    $table->decimal('curbstone_painting_m', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'submissiondate')) {
                    $table->dateTime('submissiondate')->nullable();
                }

                if (! Schema::hasColumn('road_facility_surveys', 'raw_payload')) {
                    $table->longText('raw_payload')->nullable();
                }

                $table->timestamps();
            });
        } else {
            Schema::table('road_facility_surveys', function (Blueprint $table) {
                if (! Schema::hasColumn('road_facility_surveys', 'objectid')) {
                    $table->unsignedBigInteger('objectid')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'globalid')) {
                    $table->string('globalid', 50);
                }
                if (! Schema::hasColumn('road_facility_surveys', 'field_status')) {
                    $table->string('field_status', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'governorate')) {
                    $table->string('governorate', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'municipalitie')) {
                    $table->string('municipalitie', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'neighborhood')) {
                    $table->string('neighborhood', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'assignedto')) {
                    $table->string('assignedto', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'groupnumber')) {
                    $table->integer('groupnumber')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'zone_code')) {
                    $table->string('zone_code', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'audit')) {
                    $table->string('audit', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'audit_low')) {
                    $table->string('audit_low', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'weather')) {
                    $table->string('weather', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'security_situation')) {
                    $table->string('security_situation', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'security_info')) {
                    $table->string('security_info', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'str_name')) {
                    $table->string('str_name', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'str_no')) {
                    $table->integer('str_no')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'closest_facility')) {
                    $table->string('closest_facility', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'local_authority_name')) {
                    $table->string('local_authority_name', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'local_authority_representative_name')) {
                    $table->string('local_authority_representative_name', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'representative_title')) {
                    $table->string('representative_title', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'rep_mobile_no')) {
                    $table->string('rep_mobile_no', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'road_damage_level')) {
                    $table->text('road_damage_level')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'road_access')) {
                    $table->string('road_access', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'blockage_reason')) {
                    $table->text('blockage_reason')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'potholes_exist')) {
                    $table->string('potholes_exist', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'potholes_count')) {
                    $table->integer('potholes_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'potholes_volume_m3')) {
                    $table->decimal('potholes_volume_m3', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'damaged_road_width_m')) {
                    $table->text('damaged_road_width_m')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'lane_count')) {
                    $table->string('lane_count', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'road_type_note')) {
                    $table->text('road_type_note')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'road_type')) {
                    $table->text('road_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'other_read_type')) {
                    $table->text('other_read_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'asphalt')) {
                    $table->string('asphalt', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'no_layers')) {
                    $table->integer('no_layers')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'thickness_cm')) {
                    $table->decimal('thickness_cm', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'area_m2')) {
                    $table->decimal('area_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'basecoarse')) {
                    $table->string('basecoarse', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'no_layers_001')) {
                    $table->integer('no_layers_001')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'thickness_cm_001')) {
                    $table->decimal('thickness_cm_001', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'area_m2_001')) {
                    $table->decimal('area_m2_001', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'concrete_m3')) {
                    $table->decimal('concrete_m3', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'sidewalk_interlock_m2')) {
                    $table->decimal('sidewalk_interlock_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'sidewalk_damage_type')) {
                    $table->text('sidewalk_damage_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'street_interlock_m2')) {
                    $table->decimal('street_interlock_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'curbstone_m')) {
                    $table->decimal('curbstone_m', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'unpaved_road_m2')) {
                    $table->decimal('unpaved_road_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'lighting_electrical_network')) {
                    $table->decimal('lighting_electrical_network', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'lighting_poles')) {
                    $table->string('lighting_poles', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'pole_type')) {
                    $table->text('pole_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'no_steel_pole')) {
                    $table->integer('no_steel_pole')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'no_wooden_pole')) {
                    $table->integer('no_wooden_pole')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'other_pole')) {
                    $table->string('other_pole', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'no_other_pole')) {
                    $table->integer('no_other_pole')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'lanterns_damaged')) {
                    $table->text('lanterns_damaged')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'lanterns_count')) {
                    $table->integer('lanterns_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'electric_poles_damaged')) {
                    $table->text('electric_poles_damaged')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'pole_voltage_level')) {
                    $table->string('pole_voltage_level', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'pole_material')) {
                    $table->string('pole_material', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'electric_poles_count')) {
                    $table->integer('electric_poles_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'transformers_damaged')) {
                    $table->text('transformers_damaged')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'transformers_count')) {
                    $table->integer('transformers_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'cabinets_exist')) {
                    $table->string('cabinets_exist', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'cabinets_count')) {
                    $table->integer('cabinets_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'aerial_cables_exist')) {
                    $table->string('aerial_cables_exist', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'cable_voltage_level')) {
                    $table->string('cable_voltage_level', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'aerial_cables_length')) {
                    $table->decimal('aerial_cables_length', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'stormwater_inlets_count')) {
                    $table->integer('stormwater_inlets_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'manhole_covers_missing')) {
                    $table->integer('manhole_covers_missing')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'surface_channels_length')) {
                    $table->decimal('surface_channels_length', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'water_ponding')) {
                    $table->string('water_ponding', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'traffic_signs_type')) {
                    $table->text('traffic_signs_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'traffic_signs_count')) {
                    $table->integer('traffic_signs_count')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'demolition_scope')) {
                    $table->text('demolition_scope')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'demolish_asphalt_m2')) {
                    $table->decimal('demolish_asphalt_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'demolish_base_m2')) {
                    $table->decimal('demolish_base_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'demolish_subbase_m2')) {
                    $table->decimal('demolish_subbase_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'obstacle_exist')) {
                    $table->string('obstacle_exist', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'obstacle_type')) {
                    $table->text('obstacle_type')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'obstacle_volume_m3')) {
                    $table->decimal('obstacle_volume_m3', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'handrails_damaged_mr')) {
                    $table->text('handrails_damaged_mr')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'road_painting_m2')) {
                    $table->decimal('road_painting_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'curbstone_painting_mr')) {
                    $table->decimal('curbstone_painting_mr', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'handrails_painting_mr')) {
                    $table->decimal('handrails_painting_mr', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'other_note')) {
                    $table->string('other_note', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'buried_bodies')) {
                    $table->string('buried_bodies', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'buried_bodies_est')) {
                    $table->integer('buried_bodies_est')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'uxo_present')) {
                    $table->string('uxo_present', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'final_comments')) {
                    $table->string('final_comments', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'creationdate')) {
                    $table->dateTime('creationdate')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'creator')) {
                    $table->string('creator', 128)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'editdate')) {
                    $table->dateTime('editdate')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'editor')) {
                    $table->string('editor', 128)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'sidewalk_basecourse_m2')) {
                    $table->decimal('sidewalk_basecourse_m2', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'curbstone_damaged_m')) {
                    $table->text('curbstone_damaged_m')->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'curbstone_repair_m')) {
                    $table->decimal('curbstone_repair_m', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'curbstone_painting_m')) {
                    $table->decimal('curbstone_painting_m', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('road_facility_surveys', 'submissiondate')) {
                    $table->dateTime('submissiondate')->nullable();
                }

                if (! Schema::hasColumn('road_facility_surveys', 'raw_payload')) {
                    $table->longText('raw_payload')->nullable();
                }
            });
        }

        Schema::table('road_facility_surveys', function (Blueprint $table) {
            try {
                if (Schema::hasColumn('road_facility_surveys', 'objectid')) {
                    $table->index('objectid', 'idx_road_facility_surveys_objectid');
                }
            } catch (Throwable $e) {
            }

            try {
                if (Schema::hasColumn('road_facility_surveys', 'globalid')) {
                    $table->index('globalid', 'idx_road_facility_surveys_globalid');
                }
            } catch (Throwable $e) {
            }

            try {
                if (Schema::hasColumn('road_facility_surveys', 'parentglobalid')) {
                    $table->index('parentglobalid', 'idx_road_facility_surveys_parentglobalid');
                }
            } catch (Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        // Safe rollback: do not drop table automatically.
    }
};
