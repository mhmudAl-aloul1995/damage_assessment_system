<?php

namespace App\Http\Controllers\Modules\DamageAssessment\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\DamageAssessment\AuditExportRequest;
use App\Services\DamageAssessment\Audit\AuditExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditExportController extends Controller
{
    public function __invoke(AuditExportRequest $request, AuditExportService $auditExportService): BinaryFileResponse
    {
        return $auditExportService->export($request);
    }
}
