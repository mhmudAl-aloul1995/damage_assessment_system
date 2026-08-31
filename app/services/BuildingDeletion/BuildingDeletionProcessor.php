<?php

namespace App\services\BuildingDeletion;

use App\Enums\BuildingDeletionSignatureAction;
use App\Enums\BuildingDeletionStatus;
use App\Models\AssignedAssessmentUser;
use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\Building;
use App\Models\BuildingDeletionRequest;
use App\Models\BuildingStatus;
use App\Models\BuildingStatusHistory;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\CommitteeDecision;
use App\Models\EditAssessment;
use App\Models\HousingStatus;
use App\Models\HousingStatusHistory;
use App\Models\HousingUnit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BuildingDeletionProcessor
{
    public function __construct(
        private readonly BuildingDeletionSnapshotService $snapshots,
        private readonly BuildingDeletionLayerDiscovery $layers,
        private readonly ArcgisDeletionClient $arcgis,
        private readonly BuildingDeletionAuditLogger $audit,
    ) {}

    public function process(int $requestId): void
    {
        $lock = Cache::lock('building-deletion-request:'.$requestId, 600);

        if (! $lock->get()) {
            return;
        }

        try {
            $request = BuildingDeletionRequest::query()
                ->with('signatures')
                ->findOrFail($requestId);

            if ($request->status === BuildingDeletionStatus::Completed) {
                return;
            }

            $this->run($request);
        } finally {
            $lock->release();
        }
    }

    private function run(BuildingDeletionRequest $request): void
    {
        try {
            $this->assertReady($request);

            $this->mark($request, BuildingDeletionStatus::SnapshotCreating, 'snapshot_creating', 'Creating verified deletion snapshot.');

            $snapshot = $this->snapshots->create($request, (int) ($request->gis_reviewed_by ?? $request->requested_by));

            $this->mark($request, BuildingDeletionStatus::SnapshotVerified, 'snapshot_verified', 'Snapshot was created and verified.', [
                'snapshot_id' => $snapshot->id,
                'snapshot_hash' => $snapshot->snapshot_hash,
            ]);

            $this->mark($request, BuildingDeletionStatus::Processing, 'processing', 'Deletion processing started.');

            $plan = $this->layers->deletionPlan();
            $request->forceFill([
                'deletion_plan' => $plan,
                'execution_started_at' => now(),
            ])->save();

            $gisResults = $this->deleteGis($request, $snapshot->base_data, $snapshot->audited_data);
            $localResults = $this->archiveAndRemoveLocal($request);

            $this->mark($request, BuildingDeletionStatus::Verifying, 'final_verification', 'Running final local and GIS verification.');
            $this->verifyLocalRemoval($request);

            $request->forceFill([
                'status' => BuildingDeletionStatus::Completed,
                'executed_by' => $request->gis_reviewed_by,
                'executed_at' => now(),
                'execution_results' => [
                    'gis' => $gisResults,
                    'local' => $localResults,
                    'dry_run' => (bool) config('services.arcgis.building_deletion_dry_run', false),
                ],
                'last_successful_step' => 'completed',
            ])->save();

            $this->audit->log($request, 'completed', 'success', 'Building deletion workflow completed.', $request->execution_results);
        } catch (Throwable $exception) {
            $request->refresh();
            $request->forceFill([
                'status' => BuildingDeletionStatus::Failed,
                'failed_step' => $request->last_successful_step === null ? 'initialization' : $request->last_successful_step,
                'failure_reason' => $exception->getMessage(),
                'retry_count' => $request->retry_count + 1,
            ])->save();

            $this->audit->log($request, 'failed', 'failed', $exception->getMessage());

            throw $exception;
        }
    }

    private function assertReady(BuildingDeletionRequest $request): void
    {
        if (! in_array($request->status, [BuildingDeletionStatus::Approved, BuildingDeletionStatus::Failed], true)) {
            throw new RuntimeException('Building deletion request is not approved for processing.');
        }

        if (! $request->hasSignature(BuildingDeletionSignatureAction::Requested)) {
            throw new RuntimeException('Cannot process without applicant signature.');
        }

        if (! $request->hasSignature(BuildingDeletionSignatureAction::GisApproved)) {
            throw new RuntimeException('Cannot process without GIS approval signature.');
        }
    }

    /**
     * @param  array<string, mixed>  $baseData
     * @param  array<string, mixed>  $auditedData
     * @return array<string, mixed>
     */
    private function deleteGis(BuildingDeletionRequest $request, array $baseData, array $auditedData): array
    {
        $results = [];
        $gisByLayer = [
            'base_housing_units' => data_get($baseData, 'housing_units', []),
            'audited_housing_units' => data_get($auditedData, 'housing_units', []),
            'base_buildings' => [data_get($baseData, 'building')],
            'audited_buildings' => [data_get($auditedData, 'building')],
        ];
        $layers = $this->layers->layers();

        foreach ($this->layers->deletionOrder() as $layerKey) {
            if (! isset($layers[$layerKey])) {
                continue;
            }

            $status = str_contains($layerKey, 'housing_units')
                ? BuildingDeletionStatus::GisUnitsDeleting
                : BuildingDeletionStatus::GisBuildingDeleting;

            $this->mark($request, $status, $layerKey, 'Deleting '.$layers[$layerKey]['name'].'.');

            $objectIds = $this->objectIdsForLayer($gisByLayer[$layerKey] ?? []);

            if ($objectIds === []) {
                $results[$layerKey] = [
                    'success' => true,
                    'status' => 'already_not_present',
                    'message' => 'No records were present in this layer during inventory.',
                ];

                continue;
            }

            $delete = $this->arcgis->deleteFeatures($layers[$layerKey]['url'], $objectIds);
            $this->assertDeletionResult($layerKey, $delete);

            foreach ($objectIds as $objectId) {
                if ((bool) config('services.arcgis.building_deletion_dry_run', false)) {
                    continue;
                }

                if ($this->arcgis->existsByObjectId($layers[$layerKey]['url'], $objectId)) {
                    throw new RuntimeException("GIS verification failed for {$layerKey}; ObjectID {$objectId} is still present.");
                }
            }

            $results[$layerKey] = $delete + ['verified_absent' => true];
        }

        $this->mark($request, BuildingDeletionStatus::GisUnitsDeleted, 'gis_units_deleted', 'GIS housing unit layers were processed.');
        $this->mark($request, BuildingDeletionStatus::GisBuildingDeleted, 'gis_building_deleted', 'GIS building layers were processed.');

        return $results;
    }

    /**
     * @param  array<int, mixed>  $records
     * @return array<int, int|string>
     */
    private function objectIdsForLayer(array $records): array
    {
        $objectIds = [];

        foreach ($records as $record) {
            $objectId = data_get($record, 'gis.feature.attributes.objectid')
                ?? data_get($record, 'feature.attributes.objectid');

            if ($objectId !== null && $objectId !== '') {
                $objectIds[] = $objectId;
            }
        }

        return array_values(array_unique($objectIds));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function assertDeletionResult(string $layerKey, array $result): void
    {
        if (! ($result['success'] ?? false)) {
            throw new RuntimeException("GIS deletion failed for {$layerKey}: ".($result['message'] ?? 'Unknown error'));
        }
    }

    /**
     * @return array{deleted_buildings: int, deleted_housing_units: int, deleted_audited_buildings: int, deleted_audited_housing_units: int, archived: int}
     */
    private function archiveAndRemoveLocal(BuildingDeletionRequest $request): array
    {
        $this->mark($request, BuildingDeletionStatus::LocalArchiving, 'local_archiving', 'Archiving and removing local records.');

        if ((bool) config('services.arcgis.building_deletion_dry_run', false)) {
            $this->mark($request, BuildingDeletionStatus::LocalArchived, 'local_archived', 'Dry run enabled; local database removal was simulated.');

            return [
                'deleted_buildings' => 0,
                'deleted_housing_units' => 0,
                'deleted_audited_buildings' => 0,
                'deleted_audited_housing_units' => 0,
                'archived' => 0,
            ];
        }

        return DB::transaction(function () use ($request): array {
            $building = Building::query()
                ->where('globalid', $request->building_globalid)
                ->lockForUpdate()
                ->first();

            if (! $building instanceof Building) {
                return [
                    'deleted_buildings' => 0,
                    'deleted_housing_units' => 0,
                    'deleted_audited_buildings' => 0,
                    'deleted_audited_housing_units' => 0,
                    'archived' => 0,
                ];
            }

            $units = HousingUnit::query()
                ->where('parentglobalid', $building->globalid)
                ->get();

            $unitIds = $units->pluck('id')->all();
            $unitGlobalIds = $units->pluck('globalid')->filter()->all();
            $unitObjectIds = $units->pluck('objectid')->filter()->all();
            $archived = 0;

            BuildingSurveyArchiveObject::query()->create([
                'building_objectid' => (int) ($building->objectid ?? 0),
                'building_globalid' => $building->globalid,
                'source_type' => 'building_deletion_management',
                'archived_by' => $request->gis_reviewed_by ?? $request->requested_by,
                'archived_at' => now(),
                'notes' => 'Archived by Building Deletion Management request #'.$request->id.'.',
                'building_snapshot' => $building->getAttributes(),
            ]);
            $archived++;

            foreach ($units as $unit) {
                BuildingSurveyArchiveObject::query()->create([
                    'building_objectid' => (int) ($building->objectid ?? 0),
                    'building_globalid' => $building->globalid,
                    'housing_unit_objectid' => $unit->objectid,
                    'housing_unit_globalid' => $unit->globalid,
                    'source_type' => 'building_deletion_management',
                    'archived_by' => $request->gis_reviewed_by ?? $request->requested_by,
                    'archived_at' => now(),
                    'notes' => 'Archived by Building Deletion Management request #'.$request->id.'.',
                    'building_snapshot' => $building->getAttributes(),
                    'housing_unit_snapshot' => $unit->getAttributes(),
                ]);
                $archived++;
            }

            $buildingObjectIds = array_values(array_filter([$building->objectid], fn (mixed $value): bool => $value !== null && $value !== ''));

            if ($buildingObjectIds !== []) {
                BuildingStatus::query()->whereIn('building_id', $buildingObjectIds)->delete();
                BuildingStatusHistory::query()->whereIn('building_id', $buildingObjectIds)->delete();
                AssignedAssessmentUser::query()->whereIn('building_id', $buildingObjectIds)->delete();
            }

            if ($unitObjectIds !== []) {
                HousingStatus::query()->whereIn('housing_id', $unitObjectIds)->delete();
                HousingStatusHistory::query()->whereIn('housing_id', $unitObjectIds)->delete();
            }

            EditAssessment::query()
                ->where('type', 'building_table')
                ->where('global_id', $building->globalid)
                ->delete();

            if ($unitGlobalIds !== []) {
                EditAssessment::query()
                    ->where('type', 'housing_table')
                    ->whereIn('global_id', $unitGlobalIds)
                    ->delete();
            }

            CommitteeDecision::query()
                ->where('decisionable_type', Building::class)
                ->where('decisionable_id', $building->id)
                ->delete();

            if ($unitIds !== []) {
                CommitteeDecision::query()
                    ->where('decisionable_type', HousingUnit::class)
                    ->whereIn('decisionable_id', $unitIds)
                    ->delete();
            }

            $deletedAuditedUnits = $unitGlobalIds === []
                ? 0
                : AuditedHousingUnit::query()->whereIn('globalid', $unitGlobalIds)->delete();

            $deletedAuditedBuildings = AuditedBuilding::query()
                ->where('globalid', $building->globalid)
                ->delete();

            $deletedHousingUnits = HousingUnit::query()
                ->whereIn('id', $unitIds)
                ->delete();

            $deletedBuildings = Building::query()
                ->where('id', $building->id)
                ->delete();

            $this->mark($request, BuildingDeletionStatus::LocalArchived, 'local_archived', 'Local records archived and removed.');

            return [
                'deleted_buildings' => $deletedBuildings,
                'deleted_housing_units' => $deletedHousingUnits,
                'deleted_audited_buildings' => $deletedAuditedBuildings,
                'deleted_audited_housing_units' => $deletedAuditedUnits,
                'archived' => $archived,
            ];
        });
    }

    private function verifyLocalRemoval(BuildingDeletionRequest $request): void
    {
        if ((bool) config('services.arcgis.building_deletion_dry_run', false)) {
            return;
        }

        if (Building::query()->where('globalid', $request->building_globalid)->exists()) {
            throw new RuntimeException('Local verification failed; building is still present.');
        }

        if (HousingUnit::query()->where('parentglobalid', $request->building_globalid)->exists()) {
            throw new RuntimeException('Local verification failed; housing units are still present.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function mark(BuildingDeletionRequest $request, BuildingDeletionStatus $status, string $step, string $message, ?array $payload = null): void
    {
        $request->forceFill([
            'status' => $status,
            'last_successful_step' => $step,
        ])->save();

        $this->audit->log($request, $step, $status->value, $message, $payload);
    }
}
