<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Modules\DamageAssessment\Http\Requests\AuditExportRequest;
use App\Modules\DamageAssessment\Services\Audit\AuditExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditExportController extends Controller
{
    public function __invoke(AuditExportRequest $request, AuditExportService $auditExportService): BinaryFileResponse
    {
        return $auditExportService->export($request);
    }
}
