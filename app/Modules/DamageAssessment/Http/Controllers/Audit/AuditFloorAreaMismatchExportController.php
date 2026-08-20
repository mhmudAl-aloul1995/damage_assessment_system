<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Audit;

use App\Exports\AuditFloorAreaMismatchExport;
use App\Http\Controllers\Controller;
use App\Modules\DamageAssessment\Services\Audit\AuditTableService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditFloorAreaMismatchExportController extends Controller
{
    public function __invoke(Request $request, AuditTableService $auditTableService): BinaryFileResponse
    {
        return Excel::download(
            new AuditFloorAreaMismatchExport($auditTableService->floorAreaMismatchRows($request)),
            'audit-floor-area-mismatches-'.now()->format('Y-m-d-His').'.xlsx'
        );
    }
}
