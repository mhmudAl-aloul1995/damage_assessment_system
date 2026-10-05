<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Modules\DamageAssessment\Services\Audit\AuditEngineerChangeLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditEngineerChangeLogController extends Controller
{
    public function __construct(private readonly AuditEngineerChangeLogService $changeLogService) {}

    public function __invoke(Request $request): JsonResponse
    {
        if ($request->boolean('options')) {
            return response()->json($this->changeLogService->options());
        }

        return response()->json($this->changeLogService->data($request));
    }
}
