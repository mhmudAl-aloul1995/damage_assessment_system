<?php

declare(strict_types=1);

namespace App\services;

use App\Models\RoadFacilitySurvey;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RoadFacilitySurveyImporter
{
    private const MAIN_FIELD_MAP = [
        'location' => 'location',
        'globalid' => 'globalid',
        'Field_status' => 'field_status',
        'objectid' => 'objectid',
        'Governorate' => 'governorate',
        'Municipalitie' => 'municipalitie',
        'Neighborhood' => 'neighborhood',
        'AssignedTo' => 'assigned_to',
        'GroupNumber' => 'group_number',
        'Zone_Code' => 'zone_code',
        'audit' => 'audit',
        'audit_low' => 'audit_low',
        'submissionDate' => 'submission_date',
        'Weather' => 'weather',
        'Security_Situation' => 'security_situation',
        'security_Info' => 'security_info',
        'Str_Name' => 'str_name',
        'Str_No' => 'str_no',
        'closest_facility' => 'closest_facility',
        'local_authority_name' => 'local_authority_name',
        'local_authority_representative_name' => 'local_authority_representative_name',
        'representative_title' => 'representative_title',
        'rep_mobile_no' => 'rep_mobile_no',
        'road_damage_level' => 'road_damage_level',
        'road_access' => 'road_access',
        'blockage_reason' => 'blockage_reason',
        'potholes_exist' => 'potholes_exist',
        'potholes_count' => 'potholes_count',
        'potholes_volume_m3' => 'potholes_volume_m3',
        'damaged_road_width_m' => 'damaged_road_width_m',
        'lane_count' => 'lane_count',
        'road_type_note' => 'road_type_note',
        'road_type' => 'road_type',
        'other_read_type' => 'other_read_type',
        'asphalt' => 'asphalt',
        'basecoarse' => 'basecoarse',
        'no_layers' => 'no_layers',
        'thickness_cm' => 'thickness_cm',
        'area_m2' => 'area_m2',
        'no_layers_001' => 'no_layers_001',
        'thickness_cm_001' => 'thickness_cm_001',
        'area_m2_001' => 'area_m2_001',
        'concrete_m3' => 'concrete_m3',
        'sidewalk_interlock_m2' => 'sidewalk_interlock_m2',
        'sidewalk_damage_type' => 'sidewalk_damage_type',
        'street_interlock_m2' => 'street_interlock_m2',
        'curbstone_m' => 'curbstone_m',
        'sidewalk_basecourse_m2' => 'sidewalk_basecourse_m2',
        'curbstone_damaged_m' => 'curbstone_damaged_m',
        'curbstone_repair_m' => 'curbstone_repair_m',
        'curbstone_painting_m' => 'curbstone_painting_m',
        'unpaved_road_m2' => 'unpaved_road_m2',
        'lighting_electrical_network' => 'lighting_electrical_network',
        'lighting_poles' => 'lighting_poles',
        'pole_type' => 'pole_type',
        'no_steel_pole' => 'no_steel_pole',
        'no_wooden_pole' => 'no_wooden_pole',
        'other_pole' => 'other_pole',
        'no_other_pole' => 'no_other_pole',
        'lanterns_damaged' => 'lanterns_damaged',
        'lanterns_count' => 'lanterns_count',
        'electric_poles_damaged' => 'electric_poles_damaged',
        'pole_voltage_level' => 'pole_voltage_level',
        'pole_material' => 'pole_material',
        'electric_poles_count' => 'electric_poles_count',
        'transformers_damaged' => 'transformers_damaged',
        'transformers_count' => 'transformers_count',
        'cabinets_exist' => 'cabinets_exist',
        'cabinets_count' => 'cabinets_count',
        'aerial_cables_exist' => 'aerial_cables_exist',
        'cable_voltage_level' => 'cable_voltage_level',
        'aerial_cables_length' => 'aerial_cables_length',
        'stormwater_inlets_count' => 'stormwater_inlets_count',
        'manhole_covers_missing' => 'manhole_covers_missing',
        'surface_channels_length' => 'surface_channels_length',
        'water_ponding' => 'water_ponding',
        'traffic_signs_type' => 'traffic_signs_type',
        'traffic_signs_count' => 'traffic_signs_count',
        'demolition_scope' => 'demolition_scope',
        'demolish_asphalt_m2' => 'demolish_asphalt_m2',
        'demolish_base_m2' => 'demolish_base_m2',
        'demolish_subbase_m2' => 'demolish_subbase_m2',
        'obstacle_exist' => 'obstacle_exist',
        'obstacle_type' => 'obstacle_type',
        'obstacle_volume_m3' => 'obstacle_volume_m3',
        'handrails_damaged_mr' => 'handrails_damaged_mr',
        'road_painting_m2' => 'road_painting_m2',
        'curbstone_painting_mr' => 'curbstone_painting_mr',
        'handrails_painting_mr' => 'handrails_painting_mr',
        'other_note' => 'other_note',
        'buried_bodies' => 'buried_bodies',
        'buried_bodies_est' => 'buried_bodies_est',
        'uxo_present' => 'uxo_present',
        'damge_photo_1' => 'damge_photo_1',
        'damge_photo_2' => 'damge_photo_2',
        'damge_photo_3' => 'damge_photo_3',
        'damge_photo_4' => 'damge_photo_4',
        'final_comments' => 'final_comments',
        'CreationDate' => 'creationdate',
        'Creator' => 'creator',
        'EditDate' => 'editdate',
        'Editor' => 'editor',
    ];

    private const ITEM_FIELD_MAP = [
        'item_required' => 'item_required',
        'description' => 'description',
        'unit_001' => 'unit',
        'quantity_001' => 'quantity',
        'other_comments' => 'other_comments',
    ];

    public function import(array $payload): RoadFacilitySurvey
    {
        return DB::transaction(function () use ($payload): RoadFacilitySurvey {
            $survey = RoadFacilitySurvey::query()->updateOrCreate(
                ['objectid' => Arr::get($payload, 'objectid')],
                array_merge($this->mapMainPayload($payload), [
                    'raw_payload' => $payload,
                ])
            );

            $survey->items()->delete();

            foreach ($this->extractItems($payload) as $index => $itemPayload) {
                $survey->items()->create(array_merge($this->mapItemPayload($itemPayload), [
                    'repeat_index' => $index,
                    'raw_payload' => $itemPayload,
                ]));
            }

            return $survey->fresh('items');
        });
    }

    private function mapMainPayload(array $payload): array
    {
        $mapped = [];

        foreach (self::MAIN_FIELD_MAP as $sourceKey => $targetKey) {
            if (array_key_exists($sourceKey, $payload)) {
                $mapped[$targetKey] = $payload[$sourceKey];
            }
        }

        return $mapped;
    }

    private function mapItemPayload(array $payload): array
    {
        $mapped = [];

        foreach (self::ITEM_FIELD_MAP as $sourceKey => $targetKey) {
            if (array_key_exists($sourceKey, $payload)) {
                $mapped[$targetKey] = $payload[$sourceKey];
            }
        }

        return $mapped;
    }

    private function extractItems(array $payload): array
    {
        foreach (['R2', 'items', 'repeat_items', 'other_items'] as $key) {
            $items = Arr::get($payload, $key);

            if (is_array($items)) {
                return array_values(array_filter($items, 'is_array'));
            }
        }

        return [];
    }
}
