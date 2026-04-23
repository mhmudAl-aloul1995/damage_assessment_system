<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BuildingImportController extends Controller
{
    public function import(): JsonResponse
    {
        $managerId = 1297;
        $now = Carbon::now();

        $allowedFields = [
            'audit_status',
            'final_approval_status',
            'final_approval_notes',
            'auditor_id',
            'audit_date',
            'engineer_id',
            'engineer_notes',
            'engineering_audit_status',
            'engineering_audit_date',
            'lawyer_id',
            'lawyer_notes',
            'legal_audit_status',
            'legal_audit_date',
            'objectid',
            'globalid',
            'Field_status',
            'Building_Committee_Status',
            'Unit_Committee_Status',
            'Unit_Committee_Count',
            'PARCEL_NO1',
            'BLOCK_NO1',
            'OWNER_NA',
            'units_count',
            'AssignedTo',
            'GroupNumber',
            'Zone_Code',
            'start',
            'end',
            'today',
            'username',
            'simserial',
            'subscriberid',
            'deviceid',
            'phonenumber',
            'Weather',
            'Security_Situation',
            'building_damage_status',
            'building_type',
            'building_type_other',
            'building_use',
            'building_name',
            'Date_of_damage',
            'building_material',
            'other_material',
            'building_age',
            'floor_nos',
            'ground_floor_area__m2',
            'Floor_Area_m2',
            'units_nos',
            'damaged_units_nos',
            'occupied_units_nos',
            'vacant_units_nos',
            'is_damaged_before',
            'if_damaged',
            'building_debris_exist',
            'building_debris_qty',
            'building_debris_blocking',
            'uxo_present',
            'bodies_present',
            'estimated_number_of_bodies',
            'building_status_visit',
            'building_roof_type',
            'clay_tile_area',
            'concrete_area',
            'aspestos_area',
            'scorite_area',
            'other_roof',
            'other_roof_area',
            'building_ownership',
            'owner_status',
            'building_responsible',
            'building_authorization',
            'land_fully_owned',
            'owner_name',
            'owner_id',
            'owner_mobile',
            'board1_name',
            'board1_id',
            'board1_number',
            'board2_name',
            'board2_id',
            'board2_number',
            'has_authorization_if_not_owner',
            'authorization_details',
            'is_rented',
            'tenant_names',
            'agreement_type',
            'agreement_duration',
            'has_documents',
            'doc_types_available',
            'doc_types_other',
            'no_documents_reason',
            'need_renew_docs',
            'doc_challenges',
            'doc_challenges_other',
            'has_dispute',
            'dispute_types',
            'dispute_other',
            'general_notes',
            'attach_one_photo_for_each_of_the_following_documents',
            'select_document',
            'has_elevator',
            'elevator_number',
            'elevator_status',
            'elevator_box',
            'elevator_motor',
            'has_solar',
            'solar_damage_status',
            'has_well',
            'well_damage_status',
            'has_fence',
            'fence_damage_status',
            'fence_length',
            'has_electric_room',
            'electric_room_damage_status',
            'has_sewage',
            'sewage_damage_status',
            'has_other_service',
            'other_service_details',
            'building_services_notes',
            'staircase_status',
            'Staircase_widt',
            'has_parking',
            'parking_status',
            'garage_area',
            'garage_type',
            'has_canopy',
            'canopy_status',
            'carport_length',
            'carport_width',
            'carport_area',
            'carport_height',
            'has_basement',
            'basement_status',
            'basement_area',
            'has_mezzanine',
            'mezzanine_status',
            'roof_terrace_area',
            'Comments_Recommendations',
            'CreationDate',
            'Creator',
            'EditDate',
            'Editor',
            'security_Info',
            'is_draft',
            'Service_Ownership',
            'Service_Ownership_Name',
            'land_area',
            'Governorate',
            'Municipalitie',
            'Neighborhood',
            'owner_name_1',
            'owner_mobile_1',
            'owner_mobile_v_1',
            'floor_nos_1',
            'building_address',
            'is_risk_parts',
            'elevator_damaged_doors',
        ];

        $assignedInserted = 0;
        $editInserted = 0;
        $buildingStatusesInserted = 0;
        $invalidJsonSkipped = 0;
        $skippedRows = 0;
        $missingMappedUsers = 0;

        DB::beginTransaction();

        try {
            DB::table('warda_buildings')
                ->orderBy('id')
                ->chunkById(200, function ($buildings) use ($managerId, $now, $allowedFields, &$assignedInserted, &$editInserted, &$buildingStatusesInserted, &$invalidJsonSkipped, &$skippedRows, &$missingMappedUsers) {
                    foreach ($buildings as $building) {
                        $engineeringAuditStatus = trim((string) ($building->engineering_audit_status ?? ''));
                        $legalAuditStatus = trim((string) ($building->legal_audit_status ?? ''));

                        $skipWholeRow =
                            in_array($engineeringAuditStatus, ['Assigned To Enginner', 'Pending'], true) ||
                            in_array($legalAuditStatus, ['Assigned To Lawyer', 'Pending'], true);

                        if ($skipWholeRow) {
                            $skippedRows++;
                            continue;
                        }

                        $engineerUserId = $this->mapOldUserIdToNewUserId($building->engineer_id ?? null);
                        $lawyerUserId = $this->mapOldUserIdToNewUserId($building->lawyer_id ?? null);

                        if (!empty($building->engineer_id) && empty($engineerUserId)) {
                            $missingMappedUsers++;
                        }

                        if (!empty($building->lawyer_id) && empty($lawyerUserId)) {
                            $missingMappedUsers++;
                        }

                        /*
                         * assigned_assessment_users - Engineer
                         */
                        if (!empty($engineerUserId) && !empty($building->objectid)) {
                            $exists = DB::table('assigned_assessment_users')
                                ->where('manager_id', $managerId)
                                ->where('user_id', $engineerUserId)
                                ->where('type', 'QC/QA Engineer')
                                ->where('building_id', $building->objectid)
                                ->exists();

                            if (!$exists) {
                                DB::table('assigned_assessment_users')->insert([
                                    'manager_id' => $managerId,
                                    'user_id' => $engineerUserId,
                                    'type' => 'QC/QA Engineer',
                                    'building_id' => $building->objectid,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ]);

                                $assignedInserted++;
                            }
                        }

                        /*
                         * assigned_assessment_users - Lawyer
                         */
                        if (!empty($lawyerUserId) && !empty($building->objectid)) {
                            $exists = DB::table('assigned_assessment_users')
                                ->where('manager_id', $managerId)
                                ->where('user_id', $lawyerUserId)
                                ->where('type', 'Legal Auditor')
                                ->where('building_id', $building->objectid)
                                ->exists();

                            if (!$exists) {
                                DB::table('assigned_assessment_users')->insert([
                                    'manager_id' => $managerId,
                                    'user_id' => $lawyerUserId,
                                    'type' => 'Legal Auditor',
                                    'building_id' => $building->objectid,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ]);

                                $assignedInserted++;
                            }
                        }

                        /*
                         * edit_assessment
                         */
                        if (!empty($building->globalid) && !empty($building->all_data)) {
                            $decoded = json_decode($building->all_data, true);

                            if (!is_array($decoded)) {
                                $invalidJsonSkipped++;
                            } else {
                                foreach ($allowedFields as $fieldName) {
                                    if (!property_exists($building, $fieldName)) {
                                        continue;
                                    }

                                    if (!array_key_exists($fieldName, $decoded)) {
                                        continue;
                                    }

                                    $tableValue = $this->normalizeValue($building->{$fieldName});
                                    $jsonValue = $this->normalizeValue($decoded[$fieldName]);

                                    if ($tableValue === $jsonValue) {
                                        continue;
                                    }

                                    $sameValueExists = DB::table('edit_assessments')
                                        ->where('global_id', $building->globalid)
                                        ->where('type', 'building_table')
                                        ->where('field_name', $fieldName)
                                        ->where(function ($query) use ($tableValue) {
                                            if ($tableValue === null) {
                                                $query->whereNull('field_value');
                                            } else {
                                                $query->where('field_value', $tableValue);
                                            }
                                        })
                                        ->exists();

                                    if ($sameValueExists) {
                                        continue;
                                    }

                                    DB::table('edit_assessments')->insert([
                                        'global_id' => $building->globalid,
                                        'type' => 'building_table',
                                        'field_name' => $fieldName,
                                        'field_value' => $tableValue,
                                        'user_id' => null,
                                         'created_at' => $engineerStatusAt,
                                    'updated_at' => $engineerStatusAt,
                                    ]);

                                    $editInserted++;
                                }
                            }
                        }

                        /*
                         * building_statuses - Engineer
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

                        if ($engineerStatusId !== null && !empty($building->objectid)) {
                            $engineerStatusAt = !empty($building->engineering_audit_date)
                                ? $this->parseDateValue($building->engineering_audit_date)
                                : $now;

                            $existsEngineerStatus = DB::table('building_statuses')
                                ->where('building_id', $building->objectid)
                                ->where('status_id', $engineerStatusId)
                                ->where('type', 'QC/QA Engineer')
                                ->where(function ($query) use ($engineerUserId) {
                                    if (empty($engineerUserId)) {
                                        $query->whereNull('user_id');
                                    } else {
                                        $query->where('user_id', $engineerUserId);
                                    }
                                })
                                ->where(function ($query) use ($building) {
                                    $notes = $building->engineer_notes ?? null;

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
                                DB::table('building_statuses')->insert([
                                    'building_id' => $building->objectid,
                                    'status_id' => $engineerStatusId,
                                    'user_id' => $engineerUserId,
                                    'type' => 'QC/QA Engineer',
                                    'notes' => $building->engineer_notes,
                                    'created_at' => $engineerStatusAt,
                                    'updated_at' => $engineerStatusAt,
                                ]);

                                $buildingStatusesInserted++;
                            }
                        }

                        /*
                         * building_statuses - Lawyer
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
                                    if (!empty($building->lawyer_notes)) {
                                        $lawyerStatusId = 7;
                                    }
                                    break;
                            }
                        }

                        if ($lawyerStatusId !== null && !empty($building->objectid)) {
                            $lawyerStatusAt = !empty($building->legal_audit_date)
                                ? $this->parseDateValue($building->legal_audit_date)
                                : $now;

                            $existsLawyerStatus = DB::table('building_statuses')
                                ->where('building_id', $building->objectid)
                                ->where('status_id', $lawyerStatusId)
                                ->where('type', 'Legal Auditor')
                                ->where(function ($query) use ($lawyerUserId) {
                                    if (empty($lawyerUserId)) {
                                        $query->whereNull('user_id');
                                    } else {
                                        $query->where('user_id', $lawyerUserId);
                                    }
                                })
                                ->where(function ($query) use ($building) {
                                    $notes = $building->lawyer_notes ?? null;

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
                                DB::table('building_statuses')->insert([
                                    'building_id' => $building->objectid,
                                    'status_id' => $lawyerStatusId,
                                    'user_id' => $lawyerUserId,
                                    'type' => 'Legal Auditor',
                                    'notes' => $building->lawyer_notes,
                                    'created_at' => $lawyerStatusAt,
                                    'updated_at' => $lawyerStatusAt,
                                ]);

                                $buildingStatusesInserted++;
                            }
                        }
                    }
                });

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Import completed successfully.',
                'assigned_assessment_users_inserted' => $assignedInserted,
                'edit_assessments_inserted' => $editInserted,
                'building_statuses_inserted' => $buildingStatusesInserted,
                'invalid_json_skipped' => $invalidJsonSkipped,
                'rows_skipped_by_pending_or_assigned_status' => $skippedRows,
                'missing_mapped_users' => $missingMappedUsers,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Import failed.',
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