<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HousingUnitImportController extends Controller
{
    public function import(): JsonResponse
    {
        $now = Carbon::now();

        $housingInserted = 0;
        $editInserted = 0;
        $housingStatusesInserted = 0;
        $invalidJsonSkipped = 0;
        $skippedRows = 0;

        DB::beginTransaction();
        set_time_limit(1000);

        try {

            DB::table('warda_units')
                ->orderBy('id')
                ->chunkById(200, function ($units) use (
                    $now,
                    &$housingInserted,
                    &$editInserted,
                    &$housingStatusesInserted,
                    &$invalidJsonSkipped,
                    &$skippedRows
                ) {

                    foreach ($units as $unit) {

                        $engineeringAuditStatus = trim((string)($unit->engineering_audit_status ?? ''));
                        $legalAuditStatus = trim((string)($unit->legal_audit_status ?? ''));

                        $engineerUserId = $this->mapOldUserIdToNewUserId($unit->engineer_id ?? null);
                        $lawyerUserId  = $this->mapOldUserIdToNewUserId($unit->lawyer_id ?? null);

                        /*
                        |--------------------------------------------------------------------------
                        | 1) housing_units
                        |--------------------------------------------------------------------------
                        */
                        $existingHousing = DB::table('housing_units')
                            ->where('objectid', $unit->objectid)
                            ->first();

                        if (!$existingHousing) {

                            DB::table('housing_units')->insert([
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
                            ]);

                            $housingInserted++;

                            $existingHousing = DB::table('housing_units')
                                ->where('objectid', $unit->objectid)
                                ->first();
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | 2) edit_assessments
                        |--------------------------------------------------------------------------
                        */
                        if ($existingHousing && !empty($unit->globalid) && !empty($unit->all_data)) {

                            $decoded = json_decode($unit->all_data, true);

                            if (!is_array($decoded)) {
                                $invalidJsonSkipped++;
                            } else {

                                $editAssessmentDate = $now;

                                if (!empty($unit->engineering_audit_date)) {
                                    $editAssessmentDate = $this->parseDateValue($unit->engineering_audit_date);
                                } elseif (!empty($unit->legal_audit_date)) {
                                    $editAssessmentDate = $this->parseDateValue($unit->legal_audit_date);
                                }

                                $userId = $engineerUserId ?: $lawyerUserId;

                                foreach ($decoded as $fieldName => $jsonValue) {

                                    $fieldName = $this->normalizeFieldName($fieldName);

                                    if (!property_exists($existingHousing, $fieldName)) {
                                        continue;
                                    }

                                    $originalValue = $this->normalizeValue($existingHousing->{$fieldName});
                                    $jsonValue     = $this->normalizeValue($jsonValue);

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
                                        'global_id'   => $unit->globalid,
                                        'type'        => 'housing_table',
                                        'field_name'  => $fieldName,
                                        'field_value' => $jsonValue,
                                        'user_id'     => $userId,
                                        'created_at'  => $editAssessmentDate,
                                        'updated_at'  => $editAssessmentDate,
                                    ]);

                                    $editInserted++;
                                }
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | 3) housing_statuses - Engineer
                        |--------------------------------------------------------------------------
                        */
                        if (!in_array($engineeringAuditStatus, ['Pending'], true)) {

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

                                $statusDate = !empty($unit->engineering_audit_date)
                                    ? $this->parseDateValue($unit->engineering_audit_date)
                                    : $now;

                                DB::table('housing_statuses')->insert([
                                    'housing_id'  => $existingHousing->objectid,
                                    'status_id'   => $engineerStatusId,
                                    'user_id'     => $engineerUserId,
                                    'type'        => 'QC/QA Engineer',
                                    'notes'       => $unit->engineer_notes,
                                    'created_at'  => $statusDate,
                                    'updated_at'  => $statusDate,
                                ]);

                                $housingStatusesInserted++;
                            }
                        } else {
                            $skippedRows++;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | 4) housing_statuses - Lawyer
                        |--------------------------------------------------------------------------
                        */
                        if (!in_array($legalAuditStatus, ['Assigned To Lawyer', 'Pending'], true)) {

                            $lawyerStatusId = null;

                            if (empty($lawyerUserId)) {
                                $lawyerStatusId = 6;
                            } else {
                                switch ($legalAuditStatus) {
                                    case 'Accepted by Lawyer':
                                        $lawyerStatusId = 8;
                                        break;

                                    case 'Lawyer Review need':
                                    case 'Rejected By Lawyer':
                                        $lawyerStatusId = 7;
                                        break;

                                    default:
                                        if (!empty($unit->lawyer_notes)) {
                                            $lawyerStatusId = 7;
                                        }
                                }
                            }

                            if ($lawyerStatusId !== null && $existingHousing?->objectid) {

                                $statusDate = !empty($unit->legal_audit_date)
                                    ? $this->parseDateValue($unit->legal_audit_date)
                                    : $now;

                                DB::table('housing_statuses')->insert([
                                    'housing_id'  => $existingHousing->objectid,
                                    'status_id'   => $lawyerStatusId,
                                    'user_id'     => $lawyerUserId,
                                    'type'        => 'Legal Auditor',
                                    'notes'       => $unit->lawyer_notes,
                                    'created_at'  => $statusDate,
                                    'updated_at'  => $statusDate,
                                ]);

                                $housingStatusesInserted++;
                            }
                        } else {
                            $skippedRows++;
                        }
                    }
                });

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Housing units import completed successfully.',
                'housing_units_inserted' => $housingInserted,
                'edit_assessments_inserted' => $editInserted,
                'housing_statuses_inserted' => $housingStatusesInserted,
                'invalid_json_skipped' => $invalidJsonSkipped,
                'statuses_skipped_by_pending_or_assigned_status' => $skippedRows,
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

        $map = [
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

        return $map[(int) $oldId] ?? null;
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
            $json = json_encode($value, JSON_UNESCAPED_UNICODE);
            return in_array($json, ['[]', '{}'], true) ? null : $json;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeFieldName(string $fieldName): string
    {
        $map = [
            'Damaged_Area_m2' => 'damaged_area_m2',
            'House_Unit_Ownership' => 'house_unit_ownership',
            'Occupied' => 'occupied',
            'Sex' => 'sex',
            'Number_of_people_with_disability' => 'number_of_people_with_disability',
            'Rubble_removal_is_needed' => 'rubble_removal_is_needed',
            'Activation_of_UXO_Ha_d_material_clearance' => 'activation_of_uxo_ha_d_material_clearance',
            'Is_the_Housing_Unit_or_Living_habitable' => 'is_the_housing_unit_or_living_habitable',
            'MHPSS_Experinced' => 'mhpss_experinced',
            'MHPSS_support' => 'mhpss_support',
            'CE1' => 'ce1',
            'CE2' => 'ce2',
            'CE3' => 'ce3',
            'CreationDate' => 'creationdate',
            'Creator' => 'creator',
            'EditDate' => 'editdate',
            'Editor' => 'editor',
            'Security_Situation_unit' => 'security_situation_unit',
            'Land_location_details' => 'land_location_details',
        ];

        if (isset($map[$fieldName])) {
            return $map[$fieldName];
        }

        return strtolower($fieldName);
    }

    private function parseDateValue(mixed $value): Carbon
    {
        try {
            if (is_numeric($value)) {
                return strlen((string) $value) >= 13
                    ? Carbon::createFromTimestampMs((int) $value)
                    : Carbon::createFromTimestamp((int) $value);
            }

            return Carbon::parse($value);

        } catch (\Throwable $e) {
            return now();
        }
    }
}