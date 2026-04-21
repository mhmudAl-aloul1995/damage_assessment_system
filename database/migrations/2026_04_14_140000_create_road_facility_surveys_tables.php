<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('road_facility_surveys')) {

            Schema::create('road_facility_surveys', function (Blueprint $table): void {
                $table->id();
                $table->longText('location')->nullable();
                $table->string('field_status')->nullable();
                $table->integer('objectid')->nullable()->unique();
                $table->string('governorate')->nullable();
                $table->string('municipalitie')->nullable();
                $table->string('neighborhood')->nullable();
                $table->string('assigned_to')->nullable();
                $table->integer('group_number')->nullable();
                $table->string('zone_code')->nullable();
                $table->string('audit')->nullable();
                $table->string('audit_low')->nullable();
                $table->dateTime('submission_date')->nullable();
                $table->string('weather')->nullable();
                $table->string('security_situation')->nullable();
                $table->text('security_info')->nullable();
                $table->string('str_name')->nullable();
                $table->integer('str_no')->nullable();
                $table->string('closest_facility')->nullable();
                $table->string('local_authority_name')->nullable();
                $table->string('local_authority_representative_name')->nullable();
                $table->string('representative_title')->nullable();
                $table->string('rep_mobile_no')->nullable();
                $table->string('road_damage_level')->nullable();
                $table->string('road_access')->nullable();
                $table->json('blockage_reason')->nullable();
                $table->string('potholes_exist')->nullable();
                $table->integer('potholes_count')->nullable();
                $table->decimal('potholes_volume_m3', 12, 2)->nullable();
                $table->decimal('damaged_road_width_m', 12, 2)->nullable();
                $table->string('lane_count')->nullable();
                $table->json('road_type')->nullable();
                $table->text('other_read_type')->nullable();
                $table->integer('no_layers')->nullable();
                $table->decimal('thickness_cm', 12, 2)->nullable();
                $table->decimal('area_m2', 12, 2)->nullable();
                $table->integer('no_layers_001')->nullable();
                $table->decimal('thickness_cm_001', 12, 2)->nullable();
                $table->decimal('area_m2_001', 12, 2)->nullable();
                $table->decimal('concrete_m3', 12, 2)->nullable();
                $table->decimal('sidewalk_interlock_m2', 12, 2)->nullable();
                $table->json('sidewalk_damage_type')->nullable();
                $table->decimal('street_interlock_m2', 12, 2)->nullable();
                $table->decimal('sidewalk_basecourse_m2', 12, 2)->nullable();
                $table->decimal('curbstone_damaged_m', 12, 2)->nullable();
                $table->decimal('curbstone_repair_m', 12, 2)->nullable();
                $table->decimal('curbstone_painting_m', 12, 2)->nullable();
                $table->decimal('unpaved_road_m2', 12, 2)->nullable();
                $table->decimal('lighting_electrical_network', 12, 2)->nullable();
                $table->json('pole_type')->nullable();
                $table->integer('no_steel_pole')->nullable();
                $table->integer('no_wooden_pole')->nullable();
                $table->text('other_pole')->nullable();
                $table->integer('no_other_pole')->nullable();
                $table->string('lanterns_damaged')->nullable();
                $table->integer('lanterns_count')->nullable();
                $table->string('electric_poles_damaged')->nullable();
                $table->string('pole_voltage_level')->nullable();
                $table->string('pole_material')->nullable();
                $table->integer('electric_poles_count')->nullable();
                $table->string('transformers_damaged')->nullable();
                $table->integer('transformers_count')->nullable();
                $table->string('cabinets_exist')->nullable();
                $table->integer('cabinets_count')->nullable();
                $table->string('aerial_cables_exist')->nullable();
                $table->string('cable_voltage_level')->nullable();
                $table->decimal('aerial_cables_length', 12, 2)->nullable();
                $table->integer('stormwater_inlets_count')->nullable();
                $table->integer('manhole_covers_missing')->nullable();
                $table->decimal('surface_channels_length', 12, 2)->nullable();
                $table->string('water_ponding')->nullable();
                $table->json('traffic_signs_type')->nullable();
                $table->integer('traffic_signs_count')->nullable();
                $table->string('demolition_scope')->nullable();
                $table->decimal('demolish_asphalt_m2', 12, 2)->nullable();
                $table->decimal('demolish_base_m2', 12, 2)->nullable();
                $table->decimal('demolish_subbase_m2', 12, 2)->nullable();
                $table->string('obstacle_exist')->nullable();
                $table->text('obstacle_type')->nullable();
                $table->decimal('obstacle_volume_m3', 12, 2)->nullable();
                $table->decimal('handrails_damaged_mr', 12, 2)->nullable();
                $table->decimal('road_painting_m2', 12, 2)->nullable();
                $table->decimal('curbstone_painting_mr', 12, 2)->nullable();
                $table->decimal('handrails_painting_mr', 12, 2)->nullable();
                $table->string('buried_bodies')->nullable();
                $table->integer('buried_bodies_est')->nullable();
                $table->string('uxo_present')->nullable();
                $table->text('damge_photo_1')->nullable();
                $table->text('damge_photo_2')->nullable();
                $table->text('damge_photo_3')->nullable();
                $table->text('damge_photo_4')->nullable();
                $table->text('final_comments')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('road_facility_survey_items')) {

            Schema::create('road_facility_survey_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('road_facility_survey_id')
                    ->constrained('road_facility_surveys')
                    ->cascadeOnDelete();
                $table->unsignedInteger('repeat_index')->default(0);
                $table->string('item_required')->nullable();
                $table->text('description')->nullable();
                $table->string('unit')->nullable();
                $table->integer('quantity')->nullable();
                $table->text('other_comments')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->index(['road_facility_survey_id', 'repeat_index'], 'rfs_items_survey_repeat_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('road_facility_survey_items');
        Schema::dropIfExists('road_facility_surveys');
    }
};
