<?php

namespace App\Enums;

enum BuildingDeletionStatus: string
{
    case Draft = 'draft';
    case PendingGisReview = 'pending_gis_review';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case SnapshotCreating = 'snapshot_creating';
    case SnapshotCreated = 'snapshot_created';
    case SnapshotValidating = 'snapshot_validating';
    case SnapshotVerified = 'snapshot_verified';
    case Processing = 'processing';
    case GisUnitsDeleting = 'gis_units_deleting';
    case GisUnitsDeleted = 'gis_units_deleted';
    case GisBuildingDeleting = 'gis_building_deleting';
    case GisBuildingDeleted = 'gis_building_deleted';
    case LocalArchiving = 'local_archiving';
    case LocalArchived = 'local_archived';
    case Verifying = 'verifying';
    case Completed = 'completed';
    case Failed = 'failed';
}
