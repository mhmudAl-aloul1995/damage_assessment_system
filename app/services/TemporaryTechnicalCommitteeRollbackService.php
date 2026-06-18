<?php

namespace App\services;

use App\Models\Building;
use App\Models\BuildingSurveyArchiveObject;
use App\Models\CommitteeDecision;
use App\Models\CommitteeDecisionSignature;
use App\Models\HousingUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TemporaryTechnicalCommitteeRollbackService
{
    /**
     * @return array<string, mixed>
     */
    public function rollback(bool $dryRun = true, bool $deleteDecisions = false): array
    {
        $summary = [
            'dry_run' => $dryRun,
            'archives_scanned' => 0,
            'buildings_restored_from_snapshot' => 0,
            'buildings_restored_to_committee_review' => 0,
            'housing_units_restored_from_snapshot' => 0,
            'housing_units_restored_to_committee_review' => 0,
            'parent_buildings_restored_from_snapshot' => 0,
            'parent_buildings_restored_to_completed' => 0,
            'decisions_reset' => 0,
            'decisions_deleted' => 0,
            'signatures_deleted' => 0,
            'skipped_rows' => 0,
            'skip_reasons' => [],
            'issues' => [],
        ];

        $archives = BuildingSurveyArchiveObject::query()
            ->where('source_type', 'temporary_committee_excel_archive')
            ->orderBy('id')
            ->get();

        $work = function () use ($archives, $dryRun, $deleteDecisions, &$summary): void {
            foreach ($archives as $archiveObject) {
                $summary['archives_scanned']++;
                $this->rollbackArchiveObject($archiveObject, $dryRun, $deleteDecisions, $summary);
            }
        };

        if ($dryRun) {
            $work();

            return $summary;
        }

        DB::transaction($work);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function rollbackArchiveObject(BuildingSurveyArchiveObject $archiveObject, bool $dryRun, bool $deleteDecisions, array &$summary): void
    {
        $sourceArchive = $this->sourceCommitteeArchive($archiveObject);
        $building = $this->buildingForArchive($archiveObject);
        $housingUnit = $this->housingUnitForArchive($archiveObject);
        $decision = $archiveObject->committeeDecision;

        if (! $building instanceof Building && ! $housingUnit instanceof HousingUnit) {
            $this->recordSkip($summary, 'record_not_found', [
                'archive_id' => $archiveObject->id,
                'building_objectid' => $archiveObject->building_objectid,
                'housing_unit_objectid' => $archiveObject->housing_unit_objectid,
            ]);

            return;
        }

        if ($housingUnit instanceof HousingUnit) {
            $this->restoreHousingUnit($housingUnit, $sourceArchive, $dryRun, $summary);
            $building = $building instanceof Building ? $building : $housingUnit->building;
            $this->restoreParentBuildingForUnit($building, $sourceArchive, $dryRun, $summary);
        } elseif ($building instanceof Building) {
            $this->restoreBuilding($building, $sourceArchive, $dryRun, $summary);
        }

        if ($decision instanceof CommitteeDecision) {
            $this->rollbackDecision($decision, $dryRun, $deleteDecisions, $summary);
        }
    }

    private function sourceCommitteeArchive(BuildingSurveyArchiveObject $archiveObject): ?BuildingSurveyArchiveObject
    {
        if ($archiveObject->committee_decision_id === null) {
            return null;
        }

        return BuildingSurveyArchiveObject::query()
            ->where('source_type', 'committee_decision')
            ->where('committee_decision_id', $archiveObject->committee_decision_id)
            ->where(function ($query): void {
                $query
                    ->whereNotNull('building_snapshot')
                    ->orWhereNotNull('housing_unit_snapshot');
            })
            ->latest('id')
            ->first();
    }

    private function buildingForArchive(BuildingSurveyArchiveObject $archiveObject): ?Building
    {
        if ($archiveObject->building_objectid === null) {
            return null;
        }

        return Building::query()
            ->where('objectid', $archiveObject->building_objectid)
            ->first();
    }

    private function housingUnitForArchive(BuildingSurveyArchiveObject $archiveObject): ?HousingUnit
    {
        if ($archiveObject->housing_unit_objectid === null) {
            return null;
        }

        return HousingUnit::query()
            ->where('objectid', $archiveObject->housing_unit_objectid)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function restoreBuilding(Building $building, ?BuildingSurveyArchiveObject $sourceArchive, bool $dryRun, array &$summary): void
    {
        if ($sourceArchive?->building_snapshot !== null) {
            if (! $dryRun) {
                $this->restoreModelSnapshot($building, $sourceArchive->building_snapshot);
            }

            $summary['buildings_restored_from_snapshot']++;

            return;
        }

        if (! $dryRun) {
            $building->forceFill([
                'building_damage_status' => 'committee_review',
                'field_status' => 'COMPLETED',
            ])->save();
        }

        $summary['buildings_restored_to_committee_review']++;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function restoreHousingUnit(HousingUnit $housingUnit, ?BuildingSurveyArchiveObject $sourceArchive, bool $dryRun, array &$summary): void
    {
        if ($sourceArchive?->housing_unit_snapshot !== null) {
            if (! $dryRun) {
                $this->restoreModelSnapshot($housingUnit, $sourceArchive->housing_unit_snapshot);
            }

            $summary['housing_units_restored_from_snapshot']++;

            return;
        }

        if (! $dryRun) {
            $housingUnit->forceFill([
                'unit_damage_status' => 'committee_review2',
            ])->save();
        }

        $summary['housing_units_restored_to_committee_review']++;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function restoreParentBuildingForUnit(?Building $building, ?BuildingSurveyArchiveObject $sourceArchive, bool $dryRun, array &$summary): void
    {
        if (! $building instanceof Building) {
            return;
        }

        if ($sourceArchive?->building_snapshot !== null) {
            if (! $dryRun) {
                $this->restoreModelSnapshot($building, $sourceArchive->building_snapshot);
            }

            $summary['parent_buildings_restored_from_snapshot']++;

            return;
        }

        if (! $dryRun) {
            $building->forceFill([
                'field_status' => 'COMPLETED',
            ])->save();
        }

        $summary['parent_buildings_restored_to_completed']++;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreModelSnapshot(Model $model, array $snapshot): void
    {
        $columns = Schema::connection($model->getConnectionName())
            ->getColumnListing($model->getTable());

        $attributes = Arr::only($snapshot, $columns);
        unset($attributes['id']);

        $model->forceFill($attributes)->save();
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function rollbackDecision(CommitteeDecision $decision, bool $dryRun, bool $deleteDecisions, array &$summary): void
    {
        $signatureCount = CommitteeDecisionSignature::query()
            ->where('committee_decision_id', $decision->id)
            ->count();

        if ($deleteDecisions) {
            if (! $dryRun) {
                BuildingSurveyArchiveObject::query()
                    ->where('committee_decision_id', $decision->id)
                    ->update(['committee_decision_id' => null]);

                $decision->delete();
            }

            $summary['signatures_deleted'] += $signatureCount;
            $summary['decisions_deleted']++;

            return;
        }

        if (! $dryRun) {
            CommitteeDecisionSignature::query()
                ->where('committee_decision_id', $decision->id)
                ->delete();

            $decision->forceFill([
                'status' => CommitteeDecision::STATUS_PENDING_SIGNATURES,
                'completed_at' => null,
                'arcgis_synced_at' => null,
                'arcgis_last_attempt_at' => null,
                'arcgis_sync_status' => null,
                'arcgis_last_error' => null,
                'arcgis_last_response' => null,
            ])->save();
        }

        $summary['signatures_deleted'] += $signatureCount;
        $summary['decisions_reset']++;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $issue
     */
    private function recordSkip(array &$summary, string $reasonKey, array $issue): void
    {
        $summary['skipped_rows']++;
        $summary['skip_reasons'][$reasonKey] = ($summary['skip_reasons'][$reasonKey] ?? 0) + 1;
        $summary['issues'][] = [
            'reason_key' => $reasonKey,
            ...$issue,
        ];
    }
}
