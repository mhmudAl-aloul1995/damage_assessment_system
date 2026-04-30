<?php

declare(strict_types=1);

namespace App\services;

use App\Models\PublicBuildingSurvey;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicBuildingSurveyImporter
{
    private const MAIN_FIELD_MAP = [
        'location' => 'location',
        'globalid' => 'globalid',
        'Field_status' => 'field_status',
        'PARCEL_NO1' => 'parcel_no1',
        'BLOCK_NO1' => 'block_no1',
        'OWNER_NA' => 'owner_na',
        'AssignedTo' => 'assigned_to',
        'objectid' => 'objectid',
        'Governorate' => 'governorate',
        'Municipalitie' => 'municipalitie',
        'Neighborhood' => 'neighborhood',
        'Weather' => 'weather',
        'Security_Situation' => 'security_situation',
        'security_Info' => 'security_info',
        'building_name' => 'building_name',
        'street' => 'street',
        'Address' => 'address',
        'building_damage_status' => 'building_damage_status',
        'Date_of_damage' => 'date_of_damage',
        'building_type' => 'building_type',
        'building_age' => 'building_age',
        'building_use' => 'building_use',
        'other_building_use' => 'other_building_use',
        'floor_nos' => 'floor_nos',
        'units_nos' => 'units_nos',
        'ground_floor_area__m2' => 'ground_floor_area_m2',
        'repeated_floor_area__m2' => 'repeated_floor_area_m2',
        'sector' => 'sector',
        'other_sector' => 'other_sector',
        'facility_type' => 'facility_type',
        'other_health' => 'other_health',
        'other_education' => 'other_education',
        'other_culture' => 'other_culture',
        'other_environmental' => 'other_environmental',
        'other_governmental' => 'other_governmental',
        'other_regional' => 'other_regional',
        'no_benef' => 'no_benef',
        'benef_type' => 'benef_type',
        'other_benef' => 'other_benef',
        'visit_Status' => 'visit_status',
        'is_bodies' => 'is_bodies',
        'is_UXO' => 'is_uxo',
        'occupied_stakeholders' => 'occupied_stakeholders',
        'Is_displaced' => 'is_displaced',
        'is_building_occupied' => 'is_building_occupied',
        'occupied_by' => 'occupied_by',
        'number_displaced_families' => 'number_displaced_families',
        'land_area__m2' => 'land_area_m2',
        'and_ownership' => 'and_ownership',
        'building_boundaries' => 'building_boundaries',
        'northern_side' => 'northern_side',
        'southern_side' => 'southern_side',
        'eastern_side' => 'eastern_side',
        'western_side' => 'western_side',
        'building_status' => 'building_status',
        'other_status' => 'other_status',
        'building_roof_type' => 'building_roof_type',
        'clay_tile_area' => 'clay_tile_area',
        'concrete_area' => 'concrete_area',
        'asbestos_area' => 'asbestos_area',
        'scorite_area' => 'scorite_area',
        'other_roof' => 'other_roof',
        'other_roof_area' => 'other_roof_area',
        'no_of_occupied_damaged_units' => 'no_of_occupied_damaged_units',
        'no_of_unoccupied_damaged_units' => 'no_of_unoccupied_damaged_units',
        'is_damaged_before' => 'is_damaged_before',
        'if_damaged' => 'if_damaged',
        'has_elevator' => 'has_elevator',
        'elevator_number' => 'elevator_number',
        'elevator_status' => 'elevator_status',
        'elevator_box' => 'elevator_box',
        'elevator_motor' => 'elevator_motor',
        'has_solar' => 'has_solar',
        'solar_damage_status' => 'solar_damage_status',
        'has_well' => 'has_well',
        'well_damage_status' => 'well_damage_status',
        'has_fence' => 'has_fence',
        'fence_damage_status' => 'fence_damage_status',
        'fence_length' => 'fence_length',
        'has_electric_room' => 'has_electric_room',
        'electric_room_damage_status' => 'electric_room_damage_status',
        'has_sewage' => 'has_sewage',
        'sewage_damage_status' => 'sewage_damage_status',
        'has_other_service' => 'has_other_service',
        'other_service_details' => 'other_service_details',
        'building_services_notes' => 'building_services_notes',
        'has_basement' => 'has_basement',
        'basement_status' => 'basement_status',
        'basement_area' => 'basement_area',
        'basement_finishing_type' => 'basement_finishing_type',
        'basement_use' => 'basement_use',
        'other_basement_use' => 'other_basement_use',
        'ground_floor_use' => 'ground_floor_use',
        'residential_use_area' => 'residential_use_area',
        'work_use_area' => 'work_use_area',
        'canopy_use_area' => 'canopy_use_area',
        'other_use_area' => 'other_use_area',
        'work_area_use' => 'work_area_use',
        'work_area_finishing' => 'work_area_finishing',
        'is_guard_room ' => 'is_guard_room',
        'Comments_Recommendations' => 'comments_recommendations',
        'building_image_1' => 'building_image_1',
        'building_image_2' => 'building_image_2',
        'building_image_3' => 'building_image_3',
        'CreationDate' => 'creationdate',
        'Creator' => 'creator',
        'EditDate' => 'editdate',
        'Editor' => 'editor',
    ];

    private const UNIT_FIELD_MAP = [
        'unit_name' => 'unit_name',
        'Occupied' => 'occupied',
        'unit_ownership' => 'unit_ownership',
        'other_unit_ownership' => 'other_unit_ownership',
        'unctional_use' => 'unctional_use',
        'floor_number' => 'floor_number',
        'housing_unit_number' => 'housing_unit_number',
        'The_unit_resident_time_damage' => 'the_unit_resident_time_damage',
        'Damaged_Area_m2' => 'damaged_area_m2',
        'rentee_mobile_number' => 'rentee_mobile_number',
        'work_type' => 'work_type',
        'other_work' => 'other_work',
        'external_finishing_of_the_unit' => 'external_finishing_of_the_unit',
        'other_external_finishing' => 'other_external_finishing',
        'internal_finishing_of_the_unit' => 'internal_finishing_of_the_unit',
        'percentage_of_damaged_furniture' => 'percentage_of_damaged_furniture',
        'Rubble_removal_is_needed' => 'rubble_removal_is_needed',
        'Activation_of_UXO_Ha_d_material_clearance' => 'activation_of_uxo_ha_d_material_clearance',
        'Inspection_inside_the_housing_unit' => 'inspection_inside_the_housing_unit',
        'Is_the_Housing_Unit_or_Living_habitable' => 'is_the_housing_unit_or_living_habitable',
        'select_document' => 'select_document',
        'ID_photo' => 'id_photo',
        'photo_unit_ownership' => 'photo_unit_ownership',
        'municipal_permit' => 'municipal_permit',
        'other_documents' => 'other_documents',
        'damge_photo_1' => 'damge_photo_1',
        'damge_photo_2' => 'damge_photo_2',
        'damge_photo_3' => 'damge_photo_3',
        'final_comments' => 'final_comments',
    ];

    private const UNIT_DECIMAL_CODES = [
        'DM1', 'DM2', 'DM3', 'DM4', 'DM5', 'DM6', 'DM7', 'DM8', 'DM9', 'DM10', 'DM11', 'DM12',
        'BL1', 'BL2', 'BL3', 'BL4', 'BL5', 'BL6',
        'CO1', 'CO2', 'CO3', 'CO4', 'CO5', 'CO6', 'CO7', 'CO8', 'CO9', 'CO10',
        'FN1', 'FN2', 'FN3', 'FN4', 'FN4_1', 'FN5', 'FN6', 'FN7', 'FN8', 'FN9', 'FN10', 'FN11', 'FN12', 'FN13', 'FN14', 'FN15', 'FN16', 'FN17', 'FN18', 'FN19', 'FN20', 'FN21', 'FN22', 'FN23',
        'AL1', 'AL2', 'AL3', 'AL4', 'AL5', 'AL6', 'AL7', 'AL8', 'AL9', 'AL10', 'AL11', 'AL12',
        'WD1', 'WD2', 'WD3', 'WD4', 'WD5', 'WD6',
        'MT1', 'MT2', 'MT3', 'MT4', 'MT5', 'MT6', 'MT7', 'MT8', 'MT9', 'MT10', 'MT11', 'MT12', 'MT13', 'MT14', 'MT15', 'MT16', 'MT17', 'MT18',
        'CM1', 'CM2', 'CM3', 'CM4', 'CM5', 'CM6', 'CM7', 'CM8',
        'PM1', 'PM2', 'PM3', 'PM4', 'PM5', 'PM6', 'PM7', 'PM8', 'PM9', 'PM10', 'PM11', 'PM12', 'PM13', 'PM14', 'PM15', 'PM16', 'PM17', 'PM18', 'PM19', 'PM20', 'PM21', 'PM22', 'PM23', 'PM24', 'PM25', 'PM26', 'PM27', 'PM28', 'PM29', 'PM30', 'PM31',
        'EL1', 'EL2', 'EL3', 'EL4', 'EL5', 'EL6', 'EL7', 'EL8', 'EL9', 'EL10', 'EL11', 'EL12', 'EL13', 'EL14', 'EL15', 'EL16', 'EL17', 'EL18', 'EL19', 'EL20', 'EL21', 'EL22', 'EL23', 'EL24', 'EL25',
        'quant1', 'quant2', 'quant3', 'quant4', 'quant5', 'PV6',
    ];

    private const UNIT_INTEGER_CODES = ['PV1', 'PV2', 'PV3', 'PV4', 'PV5'];

    private const UNIT_TEXT_CODES = ['item1', 'item2', 'item3', 'item4', 'item5'];

    public function import(array $payload): PublicBuildingSurvey
    {
        return DB::transaction(function () use ($payload): PublicBuildingSurvey {
            $survey = PublicBuildingSurvey::query()->updateOrCreate(
                ['objectid' => Arr::get($payload, 'objectid')],
                array_merge($this->mapMainPayload($payload), [
                    'raw_payload' => $payload,
                ])
            );

            $survey->units()->delete();

            foreach ($this->extractUnits($payload) as $index => $unitPayload) {
                $survey->units()->create(array_merge($this->mapUnitPayload($unitPayload), [
                    'repeat_index' => $index,
                    'raw_payload' => $unitPayload,
                ]));
            }

            return $survey->fresh('units');
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

    private function mapUnitPayload(array $payload): array
    {
        $mapped = [];

        foreach (self::UNIT_FIELD_MAP as $sourceKey => $targetKey) {
            if (array_key_exists($sourceKey, $payload)) {
                $mapped[$targetKey] = $payload[$sourceKey];
            }
        }

        foreach (self::UNIT_DECIMAL_CODES as $sourceKey) {
            if (array_key_exists($sourceKey, $payload)) {
                $mapped[Str::lower($sourceKey)] = $payload[$sourceKey];
            }
        }

        foreach (self::UNIT_INTEGER_CODES as $sourceKey) {
            if (array_key_exists($sourceKey, $payload)) {
                $mapped[Str::lower($sourceKey)] = $payload[$sourceKey];
            }
        }

        foreach (self::UNIT_TEXT_CODES as $sourceKey) {
            if (array_key_exists($sourceKey, $payload)) {
                $mapped[$sourceKey] = $payload[$sourceKey];
            }
        }

        return $mapped;
    }

    private function extractUnits(array $payload): array
    {
        $unitKeys = ['units', 'Unit_Information', 'unit_information', 'repeat_units'];

        foreach ($unitKeys as $unitKey) {
            $units = Arr::get($payload, $unitKey);

            if (is_array($units)) {
                return array_values(array_filter($units, 'is_array'));
            }
        }

        return [];
    }
}
