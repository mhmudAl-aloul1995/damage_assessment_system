<?php

namespace App\Modules\Reh\Services;

use App\Models\User;
use App\Modules\Reh\Jobs\RestoreRehUnit;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehCostEstimate;
use App\Modules\Reh\Models\RehCostLine;
use App\Modules\Reh\Models\RehReview;
use App\Modules\Reh\Models\RehUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RehUnitRestoration
{
    public function __construct(private RehDeletionSnapshot $snapshots, private RehArcgisClient $client, private RehUnitMaintenance $maintenance) {}

    public function start(RehAuditLog $deletion, User $user, array $data): RehAuditLog
    {
        abort_unless($user->hasRole('Database Officer'), 403);
        abort_unless($deletion->record_kind === 'unit_deletion' && $deletion->status === 'completed', 409, 'يجب حسم نتيجة الحذف أولًا.');
        $lock = Cache::lock('reh:restore-start:'.$deletion->id, 30);
        abort_unless($lock->get(), 409, 'يجري تجهيز استرجاع هذه النسخة.');
        try {
            $deletion->refresh();
            $previous = RehAuditLog::query()->where('record_kind', 'unit_restore')->where('values->deletion_log_id', $deletion->id)->latest('id')->first();
            if ($previous) {
                return $previous;
            }
            $snapshot = $this->snapshots->read($deletion);
            $this->preflight($deletion, $snapshot);
            $log = RehAuditLog::query()->create(['record_kind' => 'unit_restore', 'record_id' => $deletion->record_id, 'object_id' => $deletion->object_id,
                'global_id' => $deletion->global_id, 'layer_id' => $deletion->layer_id, 'user_id' => $user->id, 'field' => $deletion->field,
                'sensitive' => true, 'reason' => $data['reason'], 'status' => 'queued', 'message' => 'حُفظ طلب الاسترجاع؛ بانتظار المعالجة.',
                'values' => ['deletion_log_id' => $deletion->id, 'snapshot_sha256' => $deletion->values['snapshot_sha256'], 'remote_attempted' => false]]);
            $deletion->update(['values' => [...$deletion->values, 'restore_log_id' => $log->id]]);
            RestoreRehUnit::dispatch($log->id);

            return $log;
        } finally {
            $lock->release();
        }
    }

    public function resume(RehAuditLog $log, User $user): void
    {
        abort_unless($user->hasRole('Database Officer'), 403);
        abort_unless($log->record_kind === 'unit_restore', 404);
        $lock = Cache::lock('reh:restore:'.$log->id, 1900);
        abort_unless($lock->get(), 409, 'الاسترجاع قيد التنفيذ.');
        try {
            $log->refresh();
            if ($log->status === 'completed') {
                return;
            }
            $log->update(['status' => 'queued', 'message' => 'بانتظار التحقق من النتيجة واستكمال الاسترجاع.', 'values' => [...$log->values,
                'resumed_by' => $user->id, 'resumes' => [...($log->values['resumes'] ?? []), ['user_id' => $user->id, 'name' => $user->name, 'at' => now()->toIso8601String()]]]]);
            RestoreRehUnit::dispatch($log->id);
        } finally {
            $lock->release();
        }
    }

    public function process(RehAuditLog $log): void
    {
        if ($log->status === 'completed') {
            return;
        }
        try {
            $actor = User::query()->find($log->values['resumed_by'] ?? $log->user_id);
            abort_unless($actor?->hasRole('Database Officer'), 403, 'لم تعد صلاحية الاسترجاع متاحة للمستخدم.');
            $deletion = RehAuditLog::query()->where('record_kind', 'unit_deletion')->findOrFail($log->values['deletion_log_id']);
            $snapshot = $this->snapshots->read($deletion);
            abort_unless(hash_equals($log->values['snapshot_sha256'], $deletion->values['snapshot_sha256']), 409, 'تغيّرت النسخة المؤرشفة.');
            $this->preflight($deletion, $snapshot);
            $log->update(['status' => 'running', 'message' => 'جارٍ التحقق من النسخة واسترجاع السجل.']);
            $remote = null;
            if ($deletion->field !== 'database' && ! ($snapshot['remote']['absent'] ?? true)) {
                $remote = $this->client->restoreUnit($snapshot['remote'], $log->global_id, (bool) $log->values['remote_attempted'], function () use ($log): void {
                    $log->update(['values' => [...$log->values, 'remote_attempted' => true]]);
                });
            }
            $log->getConnection()->transaction(function () use ($log, $deletion, $snapshot, $remote): void {
                $this->preflight($deletion, $snapshot);
                $attributes = $snapshot['unit'];
                if ($remote) {
                    $attributes['object_id'] = (int) $remote['attributes']['objectid'];
                    $source = json_decode($attributes['source'], true, flags: JSON_THROW_ON_ERROR);
                    $source['objectid'] = $attributes['object_id'];
                    $attributes['source'] = json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                }
                $unit = RehUnit::query()->lockForUpdate()->find($attributes['id']) ?? new RehUnit;
                $this->writeRaw($unit, $attributes);
                foreach ($snapshot['reviews'] as $attributes) {
                    $this->restoreRelated(new RehReview, $attributes);
                }
                foreach ($snapshot['cost_estimates'] as $estimate) {
                    $this->restoreRelated(new RehCostEstimate, $estimate['attributes']);
                    foreach ($estimate['lines'] as $line) {
                        $this->restoreRelated(new RehCostLine, $line);
                    }
                }
                $log->update(['status' => 'completed', 'message' => 'اكتمل استرجاع الوحدة والبيانات المرتبطة بها.',
                    'values' => [...$log->values, 'restored_unit_id' => $unit->id, 'restored_object_id' => $unit->object_id, 'completed_at' => now()->toIso8601String()]]);
            });
        } catch (Throwable $exception) {
            report($exception);
            $log->refresh();
            $message = $exception instanceof RehSyncException || $exception instanceof HttpExceptionInterface ? $exception->getMessage() : 'تعذر حفظ بيانات النسخة محليًا. بقيت النسخة محفوظة؛ راجع سجل النظام قبل الاستكمال.';
            $log->update(['status' => ($log->values['remote_attempted'] ?? false) ? 'needs_review' : 'failed',
                'message' => ($log->values['remote_attempted'] ?? false) ? 'الاسترجاع يحتاج تحققًا. استخدم «التحقق والاستكمال»؛ لن تُستبدل بيانات موجودة.' : 'لم يتم الاسترجاع: '.$message]);
        }
    }

    private function preflight(RehAuditLog $deletion, array $snapshot): void
    {
        $attributes = $snapshot['unit'];
        $building = RehBuilding::query()->find($attributes['building_id']);
        abort_unless($building && $building->global_id === ($snapshot['building']['global_id'] ?? null), 409, 'المبنى الأصلي غير موجود أو تغيّر ربطه.');
        $unit = RehUnit::query()->find($attributes['id']);
        if ($deletion->field === 'arcgis') {
            abort_unless($unit && $unit->global_id === $deletion->global_id && hash_equals($deletion->values['post_delete_revision'] ?? '', $this->maintenance->revision($unit)), 409, 'السجل المحلي تغيّر منذ الحذف؛ لن تُستبدل بياناته.');
        } else {
            abort_if($unit !== null, 409, 'المعرّف المحلي مستخدم حاليًا؛ لن تُستبدل بياناته.');
        }
        abort_if(RehUnit::query()->where('id', '!=', $attributes['id'])->where(function ($query) use ($attributes): void {
            $query->where('global_id', $attributes['global_id'])->orWhere('object_id', $attributes['object_id']);
        })->exists(), 409, 'يوجد سجل حالي بنفس معرّف التأهيل أو GlobalID. راجعه قبل الاسترجاع.');
        $userIds = collect([$attributes['reviewer_id'] ?? null, ...array_column($snapshot['reviews'], 'user_id'), ...array_column(array_column($snapshot['cost_estimates'], 'attributes'), 'approved_by')])->filter(fn ($id): bool => $id !== null)->unique()->values();
        abort_unless(User::query()->whereKey($userIds->all())->count() === $userIds->count(), 409, 'أحد المستخدمين المرتبطين بالنسخة غير موجود؛ لم تُغيّر النسخة.');
        $bookIds = collect(array_column($snapshot['cost_estimates'], 'attributes'))->pluck('book_id')->unique()->values();
        if ($bookIds->isNotEmpty()) {
            abort_unless(\App\Modules\Reh\Models\RehPriceBook::query()->whereKey($bookIds->all())->count() === $bookIds->count(), 409, 'إصدار الأسعار المرتبط بالنسخة غير موجود.');
        }
        $this->checkRelated(new RehReview, $snapshot['reviews']);
        $this->checkRelated(new RehCostEstimate, array_column($snapshot['cost_estimates'], 'attributes'));
        $this->checkRelated(new RehCostLine, array_merge([], ...array_column($snapshot['cost_estimates'], 'lines')));
    }

    private function checkRelated(Model $model, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $existing = $model->newQuery()->whereIn('id', array_column($rows, 'id'))->get()->keyBy('id');
        foreach ($rows as $attributes) {
            abort_if($existing->has($attributes['id']) && $existing[$attributes['id']]->getAttributes() != $attributes, 409, 'تعارض في سجل مرتبط بالنسخة؛ لن تُستبدل بياناته.');
        }
    }

    private function restoreRelated(Model $model, array $attributes): void
    {
        $existing = $model->newQuery()->lockForUpdate()->find($attributes['id']);
        if ($existing) {
            abort_unless($existing->getAttributes() == $attributes, 409, 'تعارض في أحد السجلات المرتبطة؛ لم تُستبدل بياناته.');

            return;
        }
        $this->writeRaw($model, $attributes);
    }

    private function writeRaw(Model $model, array $attributes): void
    {
        $model->timestamps = false;
        $model->setRawAttributes($attributes);
        $model->saveQuietly();
    }
}
