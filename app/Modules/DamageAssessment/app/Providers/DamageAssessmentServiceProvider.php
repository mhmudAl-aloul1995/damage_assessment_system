<?php

namespace App\Modules\DamageAssessment\Providers;

use App\Support\Modules\ModuleServiceProvider;

class DamageAssessmentServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'damage-assessment';

    protected array $moduleCommands = [
        \App\Console\Commands\BackfillApprovedMissingCitizenSpouseNames::class,
        \App\Console\Commands\BackfillBuildingStatusHistories::class,
        \App\Console\Commands\BackfillHousingStatusHistories::class,
        \App\Console\Commands\BackfillHousingStatusHistoryTypes::class,
        \App\Console\Commands\BackfillHousingUnitNamesFromCivilRegistry::class,
        \App\Console\Commands\CompareTargetCommitteeDecisions::class,
        \App\Console\Commands\DedupeStatusHistories::class,
        \App\Console\Commands\DeleteHousingByBuildingGlobalIds::class,
        \App\Console\Commands\DeleteNullPendingAuditEdits::class,
        \App\Console\Commands\DeleteNumericallyEquivalentAuditEdits::class,
        \App\Console\Commands\DownloadHousingUnitAttachments::class,
        \App\Console\Commands\EnsureArcgisPhaseFields::class,
        \App\Console\Commands\ExportNumericallyEquivalentAuditEdits::class,
        \App\Console\Commands\GenerateArcgisMigration::class,
        \App\Console\Commands\ImportCommitteeDecisionsFromExcel::class,
        \App\Console\Commands\ImportWorkflowCommitteeDecisionsFromExcel::class,
        \App\Console\Commands\MarkPhaseExceptionsNotCompleted::class,
        \App\Console\Commands\NormalizeCsoSurveyDamageStatusInArcgis::class,
        \App\Console\Commands\ReconcileArcgisTarget::class,
        \App\Console\Commands\RefreshAuditedArcgisCache::class,
        \App\Console\Commands\RefreshMissingCitizenIdentityReport::class,
        \App\Console\Commands\RefreshMissingCitizenNameReport::class,
        \App\Console\Commands\RestoreDeletedAuditEdits::class,
        \App\Console\Commands\RollbackTemporaryTechnicalCommitteeSeed::class,
        \App\Console\Commands\RunExportDataCommand::class,
        \App\Console\Commands\SetCommitteeReviewStatuses::class,
        \App\Console\Commands\SyncArcGISBuilding::class,
        \App\Console\Commands\SyncArcGISBuildings::class,
        \App\Console\Commands\SyncArcGISHousing::class,
        \App\Console\Commands\SyncArcGISLayers::class,
        \App\Console\Commands\SyncArcGISPublicBuildingSurvey::class,
        \App\Console\Commands\SyncArcGISRoadFacilitySurvey::class,
        \App\Console\Commands\UploadAuditedToArcgis::class,
    ];
}
