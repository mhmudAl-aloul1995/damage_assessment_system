<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Modules\DamageAssessment\Exports\AuditEngineerChangeLogExport;
use App\Modules\DamageAssessment\Http\Requests\AuditEngineerChangeLogRequest;
use App\Modules\DamageAssessment\Services\Audit\AuditEngineerChangeLogService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditEngineerChangeLogController extends Controller
{
    public function __construct(private readonly AuditEngineerChangeLogService $changeLogService) {}

    public function __invoke(AuditEngineerChangeLogRequest $request): JsonResponse
    {
        if ($request->boolean('options')) {
            return response()->json($this->changeLogService->options());
        }

        return response()->json($this->changeLogService->data($request));
    }

    public function export(AuditEngineerChangeLogRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new AuditEngineerChangeLogExport($this->changeLogService->exportRows($request)),
            'audit-engineer-changes-'.now()->format('Y-m-d-His').'.xlsx'
        );
    }
}
