<?php

namespace App\Modules\Reh\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reh\Http\Requests\RehBulkDeletionRequest;
use App\Modules\Reh\Http\Requests\RehBulkSelectionRequest;
use App\Modules\Reh\Http\Requests\RehUnitDeletionRequest;
use App\Modules\Reh\Http\Requests\RehUnitMaintenanceRequest;
use App\Modules\Reh\Http\Requests\RehUnitRestorationRequest;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehUnit;
use App\Modules\Reh\Services\RehBulkUnitDeletion;
use App\Modules\Reh\Services\RehDeletionSnapshot;
use App\Modules\Reh\Services\RehFieldAudit;
use App\Modules\Reh\Services\RehSurvey;
use App\Modules\Reh\Services\RehSyncWorker;
use App\Modules\Reh\Services\RehUnitMaintenance;
use App\Modules\Reh\Services\RehUnitRestoration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RehUnitMaintenanceController extends Controller
{
    public function index(RehUnitMaintenanceRequest $request, RehUnitMaintenance $maintenance): View
    {
        $filters = $request->validated();
        $ready = app(RehFieldAudit::class)->ready();
        $logs = collect();
        $operation = null;
        $history = null;
        if ($ready) {
            $query = RehAuditLog::query()->where('record_kind', 'unit_deletion')->with('user:id,name');
            $history = (clone $query)->latest('id')->paginate(20, ['*'], 'history_page')->withQueryString()->fragment('reh-maintenance-history');
            $logs = $history->getCollection()->merge((clone $query)->whereIn('status', ['pending', 'needs_review'])->get())->unique('id')->sortByDesc('id')->values();
            $operation = RehAuditLog::query()->where('record_kind', 'unit_bulk')->where('user_id', $request->user()->id)->latest('id')->first();
        }
        $restorations = $logs->isEmpty() ? collect() : RehAuditLog::query()->where('record_kind', 'unit_restore')->whereIn('values->deletion_log_id', $logs->pluck('id'))->with('user:id,name')->get()->keyBy(fn ($log): int => (int) $log->values['deletion_log_id']);

        return view('reh::maintenance', ['filters' => $filters, 'units' => $maintenance->rows($filters), 'statistics' => $maintenance->statistics(),
            'maintenance' => $maintenance, 'creators' => $maintenance->creators(), 'survey' => app(RehSurvey::class), 'ready' => $ready,
            'logs' => $logs, 'history' => $history, 'restorations' => $restorations, 'bulkOperation' => $operation ? app(RehBulkUnitDeletion::class)->progress($operation) : null]);
    }

    public function previewBulk(RehBulkSelectionRequest $request, RehBulkUnitDeletion $bulk): JsonResponse
    {
        return response()->json($bulk->preview($request->user(), $request->validated()))->header('Cache-Control', 'private, no-store');
    }

    public function destroyBulk(RehBulkDeletionRequest $request, RehBulkUnitDeletion $bulk, RehSyncWorker $worker): JsonResponse
    {
        $log = $bulk->start($request->user(), $request->validated());
        if ($log->status === 'queued' && ! $worker->start()) {
            $log->update(['message' => 'حُفظ الطلب، لكن تعذر بدء المعالجة تلقائيًا. استخدم «بدء المعالجة» لإعادة المحاولة.']);
        }

        return response()->json(['operation' => $bulk->progress($log)], 202)->header('Cache-Control', 'private, no-store');
    }

    public function bulkStatus(RehAuditLog $log, RehBulkUnitDeletion $bulk): JsonResponse
    {
        abort_unless($log->record_kind === 'unit_bulk', 404);

        return response()->json(['operation' => $bulk->progress($log)])->header('Cache-Control', 'private, no-store');
    }

    public function startBulkWorker(RehAuditLog $log, RehSyncWorker $worker, RehBulkUnitDeletion $bulk): JsonResponse
    {
        abort_unless($log->record_kind === 'unit_bulk', 404);
        if (in_array($log->status, ['queued', 'running'], true)) {
            $lock = Cache::lock('reh:bulk-operation:'.$log->id, 1900);
            if ($lock->get()) {
                $lock->release();
                abort_unless($worker->start(), 503, 'تعذر بدء المعالجة التلقائية. تحقق من إعدادات عامل REH على السيرفر.');
            }
        }

        return response()->json(['operation' => $bulk->progress($log)])->header('Cache-Control', 'private, no-store');
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

    public function restore(RehUnitRestorationRequest $request, RehAuditLog $log, RehUnitRestoration $restoration, RehSyncWorker $worker): JsonResponse
    {
        $operation = $restoration->start($log, $request->user(), $request->validated());
        if ($operation->status === 'queued') {
            $worker->start();
        }

        return response()->json(['message' => $operation->message, 'status' => $operation->status], 202);
    }

    public function resumeRestore(Request $request, RehAuditLog $log, RehUnitRestoration $restoration, RehSyncWorker $worker): JsonResponse
    {
        $restoration->resume($log, $request->user());
        $worker->start();

        return response()->json(['message' => 'حُفظ طلب التحقق والاستكمال.'], 202);
    }

    public function snapshot(RehAuditLog $log, RehDeletionSnapshot $snapshots): JsonResponse
    {
        abort_unless($log->record_kind === 'unit_deletion', 404);

        return response()->json($snapshots->read($log))->header('Content-Disposition', 'attachment; filename="REH-snapshot-'.$log->id.'.json"')->header('Cache-Control', 'private, no-store');
    }

    public function restoreStatus(RehAuditLog $log): JsonResponse
    {
        abort_unless($log->record_kind === 'unit_restore', 404);

        return response()->json(['status' => $log->status, 'message' => $log->message,
            'url' => $log->status === 'completed' ? route('reh.units.show', $log->values['restored_unit_id']) : null])->header('Cache-Control', 'private, no-store');
    }
}
