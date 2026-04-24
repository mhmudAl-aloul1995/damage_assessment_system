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
                ->chunkById(200, function ($buildings) use (
                    $managerId,
                    $now,
                    &$assignedInserted,
                    &$editInserted,
                    &$buildingStatusesInserted,
                    &$invalidJsonSkipped,
                    &$skippedRows,
                    &$missingMappedUsers
                ) {
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
                        |--------------------------------------------------------------------------
                        | assigned_assessment_users - Engineer
                        |--------------------------------------------------------------------------
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
                        |--------------------------------------------------------------------------
                        | assigned_assessment_users - Lawyer
                        |--------------------------------------------------------------------------
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
                        |--------------------------------------------------------------------------
                        | edit_assessments
                        |--------------------------------------------------------------------------
                        */
                        if (!empty($building->globalid) && !empty($building->all_data)) {

                            $decoded = json_decode($building->all_data, true);

                            if (!is_array($decoded)) {
                                $invalidJsonSkipped++;
                            } else {

                                $editAssessmentDate = $now;

                                if (!empty($building->engineering_audit_date)) {
                                    $editAssessmentDate = $this->parseDateValue($building->engineering_audit_date);
                                } elseif (!empty($building->legal_audit_date)) {
                                    $editAssessmentDate = $this->parseDateValue($building->legal_audit_date);
                                }

                                $userId = $engineerUserId ?: $lawyerUserId;

                                foreach ($decoded as $fieldName => $jsonValue) {

                                    if (!property_exists($building, $fieldName)) {
                                        continue;
                                    }

                                    $tableValue = $this->normalizeValue($building->{$fieldName});
                                    $jsonValue = $this->normalizeValue($jsonValue);

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
                                        'user_id' => $userId,
                                        'created_at' => $editAssessmentDate,
                                        'updated_at' => $editAssessmentDate,
                                    ]);

                                    $editInserted++;
                                }
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | building_statuses - Engineer
                        |--------------------------------------------------------------------------
                        */
                        $engineerStatusId = null;

                        if (empty($engineerUserId)) {
                            $engineerStatusId = 2;
                        } else {
                            match ($engineeringAuditStatus) {
                                'Accepted by Engineer' => $engineerStatusId = 4,
                                'Engineer Review need' => $engineerStatusId = 5,
                                'Rejected By Engineer' => $engineerStatusId = 3,
                                default => null,
                            };
                        }

                        if ($engineerStatusId && !empty($building->objectid)) {

                            $statusDate = !empty($building->engineering_audit_date)
                                ? $this->parseDateValue($building->engineering_audit_date)
                                : $now;

                            DB::table('building_statuses')->insert([
                                'building_id' => $building->objectid,
                                'status_id' => $engineerStatusId,
                                'user_id' => $engineerUserId,
                                'type' => 'QC/QA Engineer',
                                'notes' => $building->engineer_notes,
                                'created_at' => $statusDate,
                                'updated_at' => $statusDate,
                            ]);

                            $buildingStatusesInserted++;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | building_statuses - Lawyer
                        |--------------------------------------------------------------------------
                        */
                        $lawyerStatusId = null;

                        if (empty($lawyerUserId)) {
                            $lawyerStatusId = 6;
                        } else {
                            match ($legalAuditStatus) {
                                'Accepted by Lawyer' => $lawyerStatusId = 8,
                                'Lawyer Review need' => $lawyerStatusId = 7,
                                'Rejected By Lawyer' => $lawyerStatusId = 7,
                                default => !empty($building->lawyer_notes) ? $lawyerStatusId = 7 : null,
                            };
                        }

                        if ($lawyerStatusId && !empty($building->objectid)) {

                            $statusDate = !empty($building->legal_audit_date)
                                ? $this->parseDateValue($building->legal_audit_date)
                                : $now;

                            DB::table('building_statuses')->insert([
                                'building_id' => $building->objectid,
                                'status_id' => $lawyerStatusId,
                                'user_id' => $lawyerUserId,
                                'type' => 'Legal Auditor',
                                'notes' => $building->lawyer_notes,
                                'created_at' => $statusDate,
                                'updated_at' => $statusDate,
                            ]);

                            $buildingStatusesInserted++;
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
        if (empty($oldId)) return null;

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

        return $map[(int)$oldId] ?? null;
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) return null;
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_array($value) || is_object($value)) return json_encode($value);
        return trim((string)$value);
    }

    private function parseDateValue(mixed $value): Carbon
    {
        try {
            if (is_numeric($value)) {
                return strlen((string)$value >= 13)
                    ? Carbon::createFromTimestampMs((int)$value)
                    : Carbon::createFromTimestamp((int)$value);
            }

            return Carbon::parse($value);

        } catch (\Throwable $e) {
            return now();
        }
    }
}