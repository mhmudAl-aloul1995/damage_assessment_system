<?php

namespace App\Modules\Reh\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reh\Http\Requests\RehUnitDeletionRequest;
use App\Modules\Reh\Http\Requests\RehUnitMaintenanceRequest;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehFieldAudit;
use App\Modules\Reh\Services\RehSurvey;
use App\Modules\Reh\Services\RehUnitMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RehUnitMaintenanceController extends Controller
{
    public function index(RehUnitMaintenanceRequest $request, RehUnitMaintenance $maintenance): View
    {
        $filters = $request->validated();
        $ready = app(RehFieldAudit::class)->ready();
        $logs = collect();
        if ($ready) {
            $query = RehAuditLog::query()->where('record_kind', 'unit_deletion')->with('user:id,name');
            $logs = (clone $query)->latest('id')->limit(20)->get()->merge((clone $query)->whereIn('status', ['pending', 'needs_review'])->get())->unique('id')->sortByDesc('id')->values();
        }

        return view('reh::maintenance', ['filters' => $filters, 'units' => $maintenance->rows($filters), 'statistics' => $maintenance->statistics(),
            'maintenance' => $maintenance, 'survey' => app(RehSurvey::class), 'ready' => $ready,
            'logs' => $logs]);
    }

    public function destroy(RehUnitDeletionRequest $request, RehUnit $unit, RehUnitMaintenance $maintenance): JsonResponse
    {
        $log = $maintenance->delete($unit, $request->user(), $request->validated());
        $request->session()->flash('reh_maintenance_message', $log->message);

        return response()->json(['message' => $log->message])->header('Cache-Control', 'private, no-store');
    }

    public function verify(Request $request, RehAuditLog $log, RehUnitMaintenance $maintenance): JsonResponse
    {
        $log = $maintenance->verify($log, $request->user());
        $request->session()->flash('reh_maintenance_message', $log->message);

        return response()->json(['message' => $log->message])->header('Cache-Control', 'private, no-store');
    }
}
