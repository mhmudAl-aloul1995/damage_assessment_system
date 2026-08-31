<?php

namespace App\Enums;

enum BuildingDeletionSignatureAction: string
{
    case Requested = 'requested';
    case GisApproved = 'gis_approved';
    case GisRejected = 'gis_rejected';
    case Returned = 'returned';
    case Executed = 'executed';
}
