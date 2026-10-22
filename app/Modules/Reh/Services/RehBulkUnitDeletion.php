<?php

namespace App\Modules\Reh\Services;

use App\Models\User;
use App\Modules\Reh\Jobs\DeleteRehUnits;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehUnit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RehBulkUnitDeletion
{
    public function __construct(private RehUnitMaintenance $maintenance) {}

    public function preview(User $user, array $data): array
    {
        abort_unless($user->hasRole('Database Officer'), 403);
        app(RehFieldAudit::class)->ensureReady();
        $selected = collect($data['units'] ?? [])->keyBy('id');
        $query = $this->maintenance->filtered($data['filters'])->without('building');
        if ($data['scope'] === 'selected') {
            $query->whereIn('reh_units.id', $selected->keys());
        } else {
            $query->whereNotIn('reh_units.id', $data['excluded'] ?? []);
        }
        $units = [];
        $query->chunkById(100, function ($records) use (&$units, $selected, $data): void {
            foreach ($records as $unit) {
                $revision = $this->maintenance->revision($unit);
                if ($data['scope'] === 'selected') {
                    abort_unless(hash_equals($selected[$unit->id]['revision'], $revision), 409, 'تغيّرت إحدى الوحدات. حدّث الصفحة وأعد التحديد.');
                }
                $units[] = ['id' => $unit->id, 'revision' => $revision, 'reference' => $unit->old_objectid, 'global_id' => $unit->global_id, 'object_id' => $unit->object_id, 'status' => 'queued'];
            }
        }, 'reh_units.id', 'id');
        abort_if($units === [], 422, 'لا توجد وحدات في هذا التحديد.');
        abort_if($data['scope'] === 'selected' && count($units) !== $selected->count(), 409, 'تغيّر نطاق الوحدات المحددة. حدّث الصفحة وأعد الاختيار.');
        $token = (string) Str::uuid();
        Cache::put('reh:bulk-preview:'.$token, ['user_id' => $user->id, 'units' => $units, 'scope' => $data['scope'], 'filters' => $data['filters']], now()->addMinutes(10));

        return ['selection_token' => $token, 'total' => count($units), 'scope' => $data['scope'], 'sample' => array_slice(array_map(fn (array $unit): array => array_intersect_key($unit, array_flip(['reference', 'object_id'])), $units), 0, 8)];
    }

    public function start(User $user, array $data): RehAuditLog
    {
        abort_unless($user->hasRole('Database Officer'), 403);
        app(RehFieldAudit::class)->ensureReady();
        $key = 'reh:bulk-preview:'.$data['selection_token'];
        $lock = Cache::lock('reh:bulk-dispatch:'.$user->id, 30);
        abort_unless($lock->get(), 409, 'يجري تجهيز نفس الطلب. انتظر لحظات.');
        try {
            $selection = Cache::get($key);
            abort_unless($selection && $selection['user_id'] === $user->id, 409, 'انتهت صلاحية التحديد. أعد تحديد الوحدات.');
            if (isset($selection['operation_id'])) {
                $existing = RehAuditLog::query()->findOrFail($selection['operation_id']);
                abort_unless($existing->field === $data['destination'] && $existing->reason === $data['reason'], 409, 'بدأ تنفيذ هذا التحديد مسبقًا؛ تابع نتيجته من سجل الحذف.');

                return $existing;
            }
            abort_if(RehAuditLog::query()->where('record_kind', 'unit_bulk')->where('user_id', $user->id)->whereIn('status', ['queued', 'running'])->exists(), 409, 'لديك طلب حذف جماعي قيد التنفيذ. تابع نتيجته قبل بدء طلب جديد.');
            $log = RehAuditLog::query()->create(['record_kind' => 'unit_bulk', 'record_id' => 0, 'object_id' => 0, 'global_id' => (string) Str::uuid(),
                'layer_id' => (int) config('reh.units_layer'), 'user_id' => $user->id, 'field' => $data['destination'], 'sensitive' => true,
                'reason' => $data['reason'], 'status' => 'queued', 'message' => 'حُفظ الطلب؛ بانتظار بدء الحذف في الخلفية.',
                'values' => ['units' => $selection['units'], 'scope' => $selection['scope'], 'filters' => $selection['filters']]]);
            Cache::put($key, [...$selection, 'operation_id' => $log->id], now()->addMinutes(10));
            try {
                DeleteRehUnits::dispatch($log->id);
            } catch (Throwable $exception) {
                $log->update(['status' => 'failed', 'message' => 'تعذر إرسال الطلب للمعالجة. لم يبدأ حذف الوحدات.']);
                report($exception);
            }

            return $log;
        } finally {
            $lock->release();
        }
    }

    public function progress(RehAuditLog $log): array
    {
        $counts = array_fill_keys(['queued', 'processing', 'completed', 'skipped', 'failed', 'needs_review'], 0);
        foreach ($log->values['units'] as $unit) {
            $counts[$unit['status']]++;
        }
        $total = array_sum($counts);
        $processed = $total - $counts['queued'] - $counts['processing'];
        $issues = array_values(array_filter($log->values['units'], fn (array $unit): bool => in_array($unit['status'], ['skipped', 'failed', 'needs_review'], true)));

        $active = in_array($log->status, ['queued', 'running'], true);

        return ['id' => $log->id, 'status' => $log->status, 'active' => $active,
            'can_start' => $active && ($log->status === 'queued' || ($log->values['waiting_for_sync'] ?? false) || $log->updated_at->lt(now()->subMinute())), 'total' => $total,
            'processed' => $processed, 'percent' => $total ? (int) floor($processed * 100 / $total) : 0, 'counts' => $counts, 'message' => $log->message,
            'issues' => array_slice(array_map(fn (array $unit): array => array_intersect_key($unit, array_flip(['reference', 'object_id', 'status', 'message', 'log_id'])), $issues), 0, 50), 'issue_count' => count($issues)];
    }

    public function processChunk(RehAuditLog $log): bool
    {
        if (! in_array($log->status, ['queued', 'running'], true)) {
            return false;
        }
        $user = $log->user;
        if (! $user?->hasRole('Database Officer')) {
            $log->update(['status' => 'failed', 'message' => 'توقف الحذف لأن صلاحية مسؤول قواعد البيانات لم تعد متاحة لصاحب الطلب.']);

            return false;
        }
        $log->update(['status' => 'running', 'message' => 'يجري حذف الوحدات المحددة مع تسجيل نتيجة كل وحدة.', 'values' => [...$log->values, 'waiting_for_sync' => false]]);
        $units = $log->values['units'];
        $handled = 0;
        foreach ($units as $index => $entry) {
            if (! in_array($entry['status'], ['queued', 'processing'], true)) {
                continue;
            }
            if ($entry['status'] === 'processing') {
                $child = RehAuditLog::query()->where('record_kind', 'unit_deletion')->where('global_id', $entry['global_id'])->where('values->bulk_operation_id', $log->id)->latest('id')->first();
                $units[$index]['status'] = match ($child?->status) {
                    'completed' => 'completed',
                    'failed', 'partial' => 'failed',
                    'pending', 'needs_review' => 'needs_review',
                    default => 'skipped',
                };
                $units[$index]['log_id'] = $child?->id;
                $units[$index]['message'] = 'توقفت محاولة سابقة؛ راجع نتيجة هذه الوحدة في السجل.';
            } else {
                $units[$index]['status'] = 'processing';
                $log->update(['values' => [...$log->values, 'units' => $units]]);
                try {
                    $unit = RehUnit::query()->find($entry['id']);
                    abort_unless($unit && $unit->global_id === $entry['global_id'] && $unit->object_id === $entry['object_id'], 409, 'الوحدة لم تعد تطابق التحديد المحفوظ.');
                    $result = $this->maintenance->delete($unit, $user, ['revision' => $entry['revision'], 'reason' => $log->reason, 'destination' => $log->field, 'bulk_operation_id' => $log->id]);
                    $units[$index]['status'] = 'completed';
                    $units[$index]['log_id'] = $result->id;
                } catch (Throwable $exception) {
                    if ($exception instanceof HttpExceptionInterface && isset($exception->getHeaders()['Retry-After'])) {
                        $units[$index]['status'] = 'queued';
                        $log->update(['values' => [...$log->values, 'units' => $units, 'waiting_for_sync' => true], 'message' => 'بانتظار انتهاء المزامنة أو التعديل الجاري.']);

                        return true;
                    }
                    $child = RehAuditLog::query()->where('record_kind', 'unit_deletion')->where('global_id', $entry['global_id'])->where('values->bulk_operation_id', $log->id)->latest('id')->first();
                    $units[$index]['status'] = $child?->status === 'needs_review' ? 'needs_review' : ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 409 ? 'skipped' : 'failed');
                    $units[$index]['log_id'] = $child?->id;
                    $units[$index]['message'] = $exception instanceof HttpExceptionInterface ? $exception->getMessage() : 'تعذر تنفيذ حذف هذه الوحدة.';
                    report($exception);
                }
            }
            $log->update(['values' => [...$log->values, 'units' => $units]]);
            if ($units[$index]['status'] === 'needs_review') {
                $log->update(['status' => 'partial', 'message' => 'توقفت الدفعة بسبب نتيجة غير مؤكدة. تحقق من سجل الوحدة؛ الوحدات المتبقية لم يبدأ حذفها.']);

                return false;
            }
            if (++$handled >= 5) {
                break;
            }
        }
        $progress = $this->progress($log);
        if ($progress['processed'] < $progress['total']) {
            return true;
        }
        $log->update(['status' => $progress['counts']['completed'] === $progress['total'] ? 'completed' : 'partial',
            'message' => 'انتهى الطلب: حُذفت '.$progress['counts']['completed'].' من '.$progress['total'].' وحدة. راجع السجل لأي وحدات لم يكتمل حذفها.']);

        return false;
    }
}
