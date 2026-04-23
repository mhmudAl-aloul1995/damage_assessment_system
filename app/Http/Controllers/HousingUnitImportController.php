<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HousingUnitImportController extends Controller
{
    public function import(): JsonResponse
    {
        $managerId = 1297;
        $now = Carbon::now();

        $allowedFields = [
            'objectid',
            'globalid',
            'parentglobalid',
            'housing_unit_type',
            'unit_damage_status',
            'floor_number',
            'housing_unit_number',
            'unit_direction',
            'damaged_area_m2',
            'infra_type2',
            'house_unit_ownership',
            'other_ownership',
            'occupied',
            'number_of_rooms',
            'identity_type1',
            'id_number1',
            'passport1',
            'other_id1',
            'unit_owner',
            'agreement_duration',
            'q_9_3_1_first_name',
            'q_9_3_2_second_name__father',
            'q_9_3_3_third_name__grandfather',
            'q_9_3_4_last_name',
            'sex',
            'mobile_number',
            'additional_mobile',
            'owner_job',
            'other_job',
            'age',
            'marital_status',
            'empty_land_rhu',
            'no_spouses',
            'spouse1',
            'spouse1_id',
            'spouse2',
            'spouse2_id',
            'spouse3',
            'spouse3_id',
            'spouse4',
            'spouse4_id',
            'are_there_people_with_disability',
            'number_of_people_with_disability',
            'handicapped_type',
            'other_handicapped',
            'is_refugee',
            'unrwa_registration_number',
            'number_of_nuclear_families',
            'mchildren_001',
            'myoung',
            'melderly',
            'fchildren',
            'fyoung_001',
            'felderly',
            'pregnant',
            'lactating',
            'the_unit_resident',
            'current_address',
            'current_residence',
            'current_residence_other',
            'shelter_name',
            'shelter_type',
            'shelter_type_other',
            'governorate',
            'locality',
            'neighborhood',
            'street',
            'closest_facility2',
            'identity_type2',
            'rentee_id_passport_number',
            'rentee_resident_full_name',
            'q_13_3_1_first_name',
            'q_13_3_2_second_name__father',
            'q_13_3_3_third_name__grandfather',
            'q_13_3_4_last_name__family',
            'rentee_mobile_number',
            'work_type',
            'other_work',
            'land_location_details',
            'external_finishing_of_the_unit',
            'other_external_finishing',
            'is_finished',
            'finishing_extent',
            'internal_finishing_of_the_unit',
            'finishing_partial_types',
            'has_fire',
            'fire_extent',
            'fire_severity',
            'fire_locations',
            'fire_rooms_count',
            'fire_area',
            'furniture_ownership',
            'tenant_name',
            'percentage_of_damaged_furniture',
            'unit_stripping',
            'unit_stripping_details',
            'stripping_area',
            'stripping_locations',
            'rubble_removal_is_needed',
            'activation_of_uxo_ha_d_material_clearance',
            'unit_support_needed',
            'is_the_housing_unit_or_living_habitable',
            'mhpss_experinced',
            'other_mhpss_exp',
            'mhpss_support',
            'other_mhpss_support',
            'community_participation',
            'ce1',
            'prefab_moving',
            'prefab_moving_maybe',
            'prefab_types',
            'other_prefab_types',
            'prefab_pref',
            'ce2',
            'reh_kitchen',
            'reh_bathroom',
            'reh_type',
            'ce3',
            'additional_comments',
            'dm1',
            'dm2',
            'dm3',
            'dm4',
            'dm5',
            'dm6',
            'dm7',
            'dm8',
            'dm9',
            'dm10',
            'dm11',
            'dm12',
            'bl2',
            'bl3',
            'bl4',
            'bl5',
            'co2',
            'co3',
            'co4',
            'co5',
            'co6',
            'co7',
            'co8',
            'co9',
            'co10',
            'fn1',
            'fn2',
            'fn3',
            'fn4',
            'fn5',
            'fn6',
            'fn7',
            'fn8',
            'fn10',
            'fn11',
            'fn12',
            'fn13',
            'fn14',
            'fn15',
            'fn16',
            'fn17',
            'fn18',
            'fn19',
            'fn20',
            'fn21',
            'fn22',
            'fn23',
            'fn24',
            'fn25',
            'fn26',
            'fn27',
            'fn28',
            'fn29',
            'fn30',
            'fn31',
            'al1',
            'al2',
            'al3',
            'al4',
            'al5',
            'al6',
            'al7',
            'al8',
            'al9',
            'al10',
            'al11',
            'wd1',
            'wd3',
            'wd4',
            'wd5',
            'wd6',
            'wd7',
            'wd8',
            'wd9',
            'wd10',
            'wd11',
            'wd12',
            'mt1',
            'mt2',
            'mt3',
            'mt4',
            'mt5',
            'mt6',
            'mt7',
            'mt8',
            'mt9',
            'mt10',
            'mt11',
            'mt12',
            'mt13',
            'mt14',
            'mt15',
            'mt16',
            'mt17',
            'mt19',
            'cm1',
            'cm2',
            'cm3',
            'cm4',
            'cm5',
            'cm6',
            'cm7',
            'cm8',
            'cm9',
            'cm10',
            'cm11',
            'cm12',
            'cm13',
            'cm14',
            'cm15',
            'cm16',
            'pm1',
            'pm2',
            'pm101',
            'pm18',
            'pm19',
            'pm3',
            'pm4',
            'pm5',
            'pm6',
            'pm7',
            'pm8',
            'pm9',
            'pm10',
            'pm11',
            'pm12',
            'pm13',
            'pm14',
            'pm15',
            'pm16',
            'pm20',
            'pm21',
            'pm22',
            'pm23',
            'pm24',
            'pm25',
            'pm26',
            'pm27',
            'pm28',
            'pm29',
            'pm30',
            'pm31',
            'pm32',
            'pm33',
            'pm34',
            'pm35',
            'pm36',
            'pm37',
            'pm38',
            'pm39',
            'pm40',
            'el1',
            'el2',
            'el3',
            'el4',
            'el5',
            'el6',
            'el7',
            'el8',
            'el9',
            'el10',
            'el11',
            'el12',
            'el13',
            'el14',
            'el15',
            'el16',
            'el17',
            'el18',
            'el19',
            'el20',
            'el21',
            'el22',
            'el23',
            'el24',
            'el25',
            'el26',
            'el27',
            'el28',
            'el29',
            'el30',
            'pv_note',
            'pv1',
            'pv2',
            'pv3',
            'pv4',
            'pv5',
            'pv6',
            'pv7',
            'pv8',
            'pv9',
            'pv10',
            'pv11',
            'pv12',
            'item1',
            'quant1',
            'item2',
            'quant2',
            'item3',
            'quant3',
            'item4',
            'quant4',
            'item5',
            'quant5',
            'final_comments',
            'creationdate',
            'creator',
            'editdate',
            'editor',
            'security_situation_unit',
            'security_unit_info',
            'ownership_image',
        ];

        $housingInserted = 0;
        $assignedInserted = 0;
        $editInserted = 0;
        $housingStatusesInserted = 0;
        $invalidJsonSkipped = 0;
        $skippedRows = 0;
        $missingMappedUsers = 0;

        DB::beginTransaction();
        set_time_limit(1000);
        try {
            DB::table('warda_units')
                ->orderBy('id')
                ->chunkById(200, function ($units) use ($managerId, $now, $allowedFields, &$housingInserted, &$assignedInserted, &$editInserted, &$housingStatusesInserted, &$invalidJsonSkipped, &$skippedRows, &$missingMappedUsers) {
                    foreach ($units as $unit) {
                        $engineeringAuditStatus = trim((string) ($unit->engineering_audit_status ?? ''));
                        $legalAuditStatus = trim((string) ($unit->legal_audit_status ?? ''));

                        $skipWholeRow =
                            in_array($engineeringAuditStatus, ['Assigned To Enginner', 'Pending'], true) ||
                            in_array($legalAuditStatus, ['Assigned To Lawyer', 'Pending'], true);

                        if ($skipWholeRow) {
                            $skippedRows++;
                            continue;
                        }

                        $engineerUserId = $this->mapOldUserIdToNewUserId($unit->engineer_id ?? null);
                        $lawyerUserId = $this->mapOldUserIdToNewUserId($unit->lawyer_id ?? null);

                        if (!empty($unit->engineer_id) && empty($engineerUserId)) {
                            $missingMappedUsers++;
                        }

                        if (!empty($unit->lawyer_id) && empty($lawyerUserId)) {
                            $missingMappedUsers++;
                        }

                        /*
                         * 1) housing_units
                         */
                        $existingHousing = DB::table('housing_units')
                            ->where('objectid', $unit->objectid)
                            ->first();

                        if (!$existingHousing) {
                            $insertData = [
                                'objectid' => $unit->objectid,
                                'globalid' => $unit->globalid,
                                'parentglobalid' => $unit->parentglobalid,
                                'housing_unit_type' => $unit->housing_unit_type,
                                'unit_damage_status' => $unit->unit_damage_status,
                                'floor_number' => $unit->floor_number,
                                'housing_unit_number' => $unit->housing_unit_number,
                                'unit_direction' => $unit->unit_direction,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];

                            foreach ($allowedFields as $field) {
                                if (property_exists($unit, $field) && !array_key_exists($field, $insertData)) {
                                    $insertData[$field] = $unit->{$field};
                                }
                            }

                            DB::table('housing_units')->insert($insertData);
                            $housingInserted++;

                            $existingHousing = DB::table('housing_units')
                                ->where('objectid', $unit->objectid)
                                ->first();
                        }

                        /*
                         * 2) assigned_assessment_users - Engineer
                         */
                        if (!empty($engineerUserId) && !empty($unit->building_id)) {
                            $exists = DB::table('assigned_assessment_users')
                                ->where('manager_id', $managerId)
                                ->where('user_id', $engineerUserId)
                                ->where('type', 'QC/QA Engineer')
                                ->where('building_id', $unit->building_id)
                                ->exists();

                            if (!$exists) {
                                DB::table('assigned_assessment_users')->insert([
                                    'manager_id' => $managerId,
                                    'user_id' => $engineerUserId,
                                    'type' => 'QC/QA Engineer',
                                    'building_id' => $unit->building_id,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ]);

                                $assignedInserted++;
                            }
                        }

                        /*
                         * 3) assigned_assessment_users - Lawyer
                         */
                        if (!empty($lawyerUserId) && !empty($unit->building_id)) {
                            $exists = DB::table('assigned_assessment_users')
                                ->where('manager_id', $managerId)
                                ->where('user_id', $lawyerUserId)
                                ->where('type', 'Legal Auditor')
                                ->where('building_id', $unit->building_id)
                                ->exists();

                            if (!$exists) {
                                DB::table('assigned_assessment_users')->insert([
                                    'manager_id' => $managerId,
                                    'user_id' => $lawyerUserId,
                                    'type' => 'Legal Auditor',
                                    'building_id' => $unit->building_id,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ]);

                                $assignedInserted++;
                            }
                        }

                        /*
                         * 4) edit_assessments
                         * الأصل من housing_units
                         * المعدل من warda_units.all_data
                         * المخزن = قيمة JSON المعدلة
                         */
                        if ($existingHousing && !empty($unit->globalid) && !empty($unit->all_data)) {
                            $decoded = json_decode($unit->all_data, true);

                            if (!is_array($decoded)) {
                                $invalidJsonSkipped++;
                            } else {
                                foreach ($allowedFields as $fieldName) {
                                    if (!property_exists($existingHousing, $fieldName)) {
                                        continue;
                                    }

                                    if (!array_key_exists($fieldName, $decoded)) {
                                        continue;
                                    }

                                    $originalValue = $this->normalizeValue($existingHousing->{$fieldName});
                                    $jsonValue = $this->normalizeValue($decoded[$fieldName]);

                                    if ($originalValue === $jsonValue) {
                                        continue;
                                    }

                                    $sameValueExists = DB::table('edit_assessments')
                                        ->where('global_id', $unit->globalid)
                                        ->where('type', 'housing_table')
                                        ->where('field_name', $fieldName)
                                        ->where(function ($query) use ($jsonValue) {
                                            if ($jsonValue === null) {
                                                $query->whereNull('field_value');
                                            } else {
                                                $query->where('field_value', $jsonValue);
                                            }
                                        })
                                        ->exists();

                                    if ($sameValueExists) {
                                        continue;
                                    }

                                    DB::table('edit_assessments')->insert([
                                        'global_id' => $unit->globalid,
                                        'type' => 'housing_table',
                                        'field_name' => $fieldName,
                                        'field_value' => $jsonValue,
                                        'user_id' => null,
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ]);

                                    $editInserted++;
                                }
                            }
                        }

                        /*
                         * 5) housing_statuses - Engineer
                         */
                        $engineerStatusId = null;

                        if (empty($engineerUserId)) {
                            $engineerStatusId = 2;
                        } else {
                            switch ($engineeringAuditStatus) {
                                case 'Accepted by Engineer':
                                    $engineerStatusId = 4;
                                    break;
                                case 'Engineer Review need':
                                    $engineerStatusId = 5;
                                    break;
                                case 'Rejected By Engineer':
                                    $engineerStatusId = 3;
                                    break;
                            }
                        }

                        if ($engineerStatusId !== null && $existingHousing?->objectid) {
                            $engineerStatusAt = !empty($unit->engineering_audit_date)
                                ? $this->parseDateValue($unit->engineering_audit_date)
                                : $now;

                            $existsEngineerStatus = DB::table('housing_statuses')
                                ->where('housing_id', $existingHousing->objectid)
                                ->where('status_id', $engineerStatusId)
                                ->where('type', 'QC/QA Engineer')
                                ->where(function ($query) use ($engineerUserId) {
                                    if (empty($engineerUserId)) {
                                        $query->whereNull('user_id');
                                    } else {
                                        $query->where('user_id', $engineerUserId);
                                    }
                                })
                                ->where(function ($query) use ($unit) {
                                    $notes = $unit->engineer_notes ?? null;

                                    if ($notes === null || $notes === '') {
                                        $query->where(function ($q) {
                                            $q->whereNull('notes')->orWhere('notes', '');
                                        });
                                    } else {
                                        $query->where('notes', $notes);
                                    }
                                })
                                ->exists();

                            if (!$existsEngineerStatus) {
                                DB::table('housing_statuses')->insert([
                                    'housing_id' => $existingHousing->objectid,
                                    'status_id' => $engineerStatusId,
                                    'user_id' => $engineerUserId,
                                    'type' => 'QC/QA Engineer',
                                    'notes' => $unit->engineer_notes,
                                    'created_at' => $engineerStatusAt,
                                    'updated_at' => $engineerStatusAt,
                                ]);

                                $housingStatusesInserted++;
                            }
                        }

                        /*
                         * 6) housing_statuses - Lawyer
                         */
                        $lawyerStatusId = null;

                        if (empty($lawyerUserId)) {
                            $lawyerStatusId = 6;
                        } else {
                            switch ($legalAuditStatus) {
                                case 'Accepted by Lawyer':
                                    $lawyerStatusId = 8;
                                    break;
                                case 'Lawyer Review need':
                                    $lawyerStatusId = 7;
                                    break;
                                case 'Rejected By Lawyer':
                                    $lawyerStatusId = 7;
                                    break;
                                default:
                                    if (!empty($unit->lawyer_notes)) {
                                        $lawyerStatusId = 7;
                                    }
                                    break;
                            }
                        }

                        if ($lawyerStatusId !== null && $existingHousing?->objectid) {
                            $lawyerStatusAt = !empty($unit->legal_audit_date)
                                ? $this->parseDateValue($unit->legal_audit_date)
                                : $now;

                            $existsLawyerStatus = DB::table('housing_statuses')
                                ->where('housing_id', $existingHousing->objectid)
                                ->where('status_id', $lawyerStatusId)
                                ->where('type', 'Legal Auditor')
                                ->where(function ($query) use ($lawyerUserId) {
                                    if (empty($lawyerUserId)) {
                                        $query->whereNull('user_id');
                                    } else {
                                        $query->where('user_id', $lawyerUserId);
                                    }
                                })
                                ->where(function ($query) use ($unit) {
                                    $notes = $unit->lawyer_notes ?? null;

                                    if ($notes === null || $notes === '') {
                                        $query->where(function ($q) {
                                            $q->whereNull('notes')->orWhere('notes', '');
                                        });
                                    } else {
                                        $query->where('notes', $notes);
                                    }
                                })
                                ->exists();

                            if (!$existsLawyerStatus) {
                                DB::table('housing_statuses')->insert([
                                    'housing_id' => $existingHousing->objectid,
                                    'status_id' => $lawyerStatusId,
                                    'user_id' => $lawyerUserId,
                                    'type' => 'Legal Auditor',
                                    'notes' => $unit->lawyer_notes,
                                    'created_at' => $lawyerStatusAt,
                                    'updated_at' => $lawyerStatusAt,
                                ]);

                                $housingStatusesInserted++;
                            }
                        }
                    }
                });

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Housing units import completed successfully.',
                'housing_units_inserted' => $housingInserted,
                'assigned_assessment_users_inserted' => $assignedInserted,
                'edit_assessmentss_inserted' => $editInserted,
                'housing_statuses_inserted' => $housingStatusesInserted,
                'invalid_json_skipped' => $invalidJsonSkipped,
                'rows_skipped_by_pending_or_assigned_status' => $skippedRows,
                'missing_mapped_users' => $missingMappedUsers,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Housing units import failed.',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function mapOldUserIdToNewUserId(mixed $oldId): ?int
    {
        if (empty($oldId)) {
            return null;
        }

        $oldId = (int) $oldId;

        $manualUserMap = [
            236 => 1342,
            237 => 1341,
            238 => 1340,
            239 => 1343,
            240 => 1344,
            241 => 1293,
            242 => 1282,
            243 => 1295,
            245 => 1335,
            246 => 1334,
            247 => 1336,
            248 => 1276,
        ];

        return $manualUserMap[$oldId] ?? null;
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return trim((string) $value);
    }

    private function parseDateValue(mixed $value): Carbon
    {
        if (empty($value)) {
            return now();
        }

        try {
            if (is_numeric($value)) {
                $value = (string) $value;

                if (strlen($value) >= 13) {
                    return Carbon::createFromTimestampMs((int) $value);
                }

                return Carbon::createFromTimestamp((int) $value);
            }

            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return now();
        }
    }
}