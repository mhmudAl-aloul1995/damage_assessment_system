<?php

namespace App\Modules\Reh\Services;

use App\Models\User;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehCostEstimate;
use App\Modules\Reh\Models\RehUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RehUnitMaintenance
{
    private ?string $creatorSql = null;

    public function __construct(private RehArcgisClient $client, private RehFieldAudit $audit) {}

    private function groups(): Builder
    {
        return RehUnit::query()->selectRaw('TRIM(reference_id) AS reference_key, COUNT(*) AS reference_count')
            ->whereRaw("NULLIF(TRIM(reference_id), '') IS NOT NULL")->groupByRaw('TRIM(reference_id)');
    }

    private function query(): Builder
    {
        return RehUnit::query()->leftJoinSub($this->groups(), 'reference_groups', function (JoinClause $join): void {
            $join->whereRaw('TRIM(reh_units.reference_id) = reference_groups.reference_key');
        })->select('reh_units.*')->addSelect('reference_groups.reference_count');
    }

    public function filtered(array $filters): Builder
    {
        $query = $this->query()->with('building');
        match ($filters['kind'] ?? 'duplicate') {
            'duplicate' => $query->where('reference_groups.reference_count', '>', 1),
            'missing' => $query->whereRaw("NULLIF(TRIM(reh_units.reference_id), '') IS NULL"),
            default => null,
        };
        match ($filters['code_status'] ?? 'all') {
            'available' => $query->whereRaw("NULLIF(TRIM(reh_units.code), '') IS NOT NULL"),
            'missing' => $query->whereRaw("NULLIF(TRIM(reh_units.code), '') IS NULL"),
            default => null,
        };
        if (filled($filters['creator'] ?? null)) {
            $query->whereRaw($this->creatorExpression().' = ?', [trim($filters['creator'])]);
        }
        if (($filters['q'] ?? '') !== '') {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('reh_units.reference_id', trim($filters['q']));
                if (ctype_digit(trim($filters['q']))) {
                    $query->orWhere('reh_units.object_id', trim($filters['q']));
                }
            });
        }

        return $query;
    }

    public function rows(array $filters): LengthAwarePaginator
    {
        return $this->filtered($filters)->orderByRaw("CASE WHEN NULLIF(TRIM(reh_units.reference_id), '') IS NULL THEN 1 ELSE 0 END")
            ->orderByRaw('CAST(TRIM(reh_units.reference_id) AS DECIMAL(30, 0))')->orderByRaw('TRIM(reh_units.reference_id)')
            ->orderBy('reh_units.object_id')->paginate((int) ($filters['per_page'] ?? 50))->withQueryString();
    }

    public function creators(): array
    {
        return RehUnit::query()->selectRaw($this->creatorExpression().' AS record_creator')
            ->whereRaw($this->creatorExpression().' IS NOT NULL')->distinct()->orderBy('record_creator')->pluck('record_creator')->all();
    }

    public function creatorName(RehUnit $unit): string
    {
        foreach ([$unit->creator, $unit->source['Creator'] ?? null, $unit->source['creator'] ?? null] as $creator) {
            if (filled($creator)) {
                return trim((string) $creator);
            }
        }

        return 'غير متوفر';
    }

    private function creatorExpression(): string
    {
        if ($this->creatorSql !== null) {
            return $this->creatorSql;
        }
        $connection = (new RehUnit)->getConnection();
        $grammar = $connection->getQueryGrammar();
        $columns = [];
        if ($connection->getSchemaBuilder()->hasColumn('reh_units', 'creator')) {
            $columns[] = "NULLIF(TRIM(reh_units.creator), '')";
        }
        foreach (['Creator', 'creator'] as $key) {
            $selector = $grammar->wrap('reh_units.source->'.$key);
            $columns[] = "NULLIF(NULLIF(TRIM($selector), ''), 'null')";
        }

        return $this->creatorSql = 'COALESCE('.implode(', ', $columns).')';
    }

    public function statistics(): array
    {
        return ['all' => RehUnit::query()->count(), 'duplicate' => $this->query()->where('reference_groups.reference_count', '>', 1)->count(),
            'groups' => $this->groups()->havingRaw('COUNT(*) > 1')->get()->count(),
            'missing' => RehUnit::query()->whereRaw("NULLIF(TRIM(reference_id), '') IS NULL")->count()];
    }

    public function revision(RehUnit $unit): string
    {
        $attributes = $unit->getAttributes();
        unset($attributes['reference_count']);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    public function delete(RehUnit $unit, User $user, array $data): RehAuditLog
    {
        abort_unless($user->hasRole('Database Officer'), 403);
        $this->audit->ensureReady();
        $lock = Cache::lock('reh:sync:execution', 1900);
        abort_unless($lock->get(), 409, 'هناك مزامنة أو تعديل جارٍ. انتظر انتهاءه قبل الحذف.', ['Retry-After' => '30']);
        $log = null;
        $attempted = false;
        $remoteDeleted = false;
        try {
            $unit->refresh();
            abort_unless(hash_equals($this->revision($unit), $data['revision']), 409, 'تغيّرت الوحدة. حدّث الصفحة وراجع بياناتها قبل الحذف.');
            abort_if(RehAuditLog::query()->where('global_id', $unit->global_id)->whereIn('record_kind', ['unit', 'unit_deletion'])->whereIn('status', ['pending', 'needs_review'])->exists(), 409, 'توجد عملية غير محسومة لهذه الوحدة. تحقق من نتيجتها في السجل أولًا.');
            $estimates = app(RehPricing::class)->ready() ? RehCostEstimate::query()->where('unit_id', $unit->id)->with('lines')->get()->toArray() : [];
            $log = RehAuditLog::query()->create([
                'record_kind' => 'unit_deletion', 'record_id' => $unit->id, 'object_id' => $unit->object_id, 'global_id' => $unit->global_id,
                'layer_id' => (int) config('reh.units_layer'), 'user_id' => $user->id, 'field' => $data['destination'], 'sensitive' => true,
                'reason' => $data['reason'], 'values' => ['revision' => $data['revision'], 'reference' => $unit->old_objectid,
                    'before' => $unit->attributesToArray(), 'reviews' => $unit->reviews()->get()->toArray(), 'cost_estimates' => $estimates,
                    'remote_deleted' => false, 'local_deleted' => false, 'bulk_operation_id' => $data['bulk_operation_id'] ?? null],
            ]);
            $snapshots = app(RehDeletionSnapshot::class);
            $snapshot = $snapshots->local($unit);
            $snapshots->store($log, $snapshot);
            if ($data['destination'] !== 'database') {
                $snapshot['remote'] = $snapshots->remote($unit, $log);
                $snapshots->store($log, $snapshot);
                $attempted = true;
                $this->client->deleteUnit($unit->object_id, $unit->global_id);
                $remoteDeleted = true;
                $log->update(['values' => [...$log->values, 'remote_deleted' => true]]);
            }
            $this->applyLocal($log);

            return $log->fresh();
        } catch (Throwable $exception) {
            if (! $log) {
                throw $exception;
            }
            $uncertain = $remoteDeleted || ($attempted && ! ($exception instanceof RehSyncException && $exception->getCode() === 2002));
            $message = $uncertain ? 'لم تُحسم العملية في المصدرين. تحقق من النتيجة من سجل الحذف قبل أي محاولة أخرى.' : 'لم يتم الحذف؛ بقيت الوحدة في قاعدة البيانات. راجع الاتصال وصلاحية حذف السجل.';
            $log->update(['status' => $uncertain ? 'needs_review' : 'failed', 'message' => $message]);
            report($exception);
            abort($uncertain ? 502 : 422, $message);
        } finally {
            $lock->release();
        }
    }

    public function verify(RehAuditLog $log, User $user): RehAuditLog
    {
        abort_unless($user->hasRole('Database Officer') && $log->record_kind === 'unit_deletion', 403);
        $lock = Cache::lock('reh:sync:execution', 600);
        abort_unless($lock->get(), 409, 'انتظر انتهاء المزامنة أو التعديل الجاري.');
        try {
            $log->refresh();
            if ($log->status === 'completed') {
                return $log;
            }
            abort_unless(in_array($log->status, ['pending', 'needs_review'], true), 409);
            if ($log->field === 'database' || ! $this->client->unitAbsent($log->object_id, $log->global_id)) {
                $log->update(['status' => 'failed', 'message' => 'تم التحقق: لم يكتمل الحذف. راجع الوحدة قبل تقديم طلب حذف جديد.']);

                return $log;
            }
            $log->update(['values' => [...$log->values, 'remote_deleted' => true]]);
            $unit = RehUnit::query()->find($log->record_id);
            if ($unit && ! hash_equals($log->values['revision'], $this->revision($unit))) {
                $log->update(['status' => 'partial', 'message' => 'تأكد حذف المصدر، لكن بيانات الوحدة المحلية تغيّرت. راجعها ثم قدّم طلب حذف محلي جديد إذا لزم.']);

                return $log;
            }
            $this->applyLocal($log);

            return $log->fresh();
        } catch (RehSyncException $exception) {
            report($exception);
            abort(502, 'تعذر التحقق من ArcGIS حاليًا. تبقى العملية معلّقة حتى تأكيد نتيجتها.');
        } finally {
            $lock->release();
        }
    }

    private function applyLocal(RehAuditLog $log): void
    {
        $log->getConnection()->transaction(function () use ($log): void {
            $unit = RehUnit::query()->lockForUpdate()->find($log->record_id);
            if ($unit) {
                abort_unless(hash_equals($log->values['revision'], $this->revision($unit)), 409, 'تغيّرت الوحدة محليًا. يلزم مراجعة السجل قبل استكمال العملية.');
                if ($log->field !== 'arcgis') {
                    $reviews = $unit->reviews()->lockForUpdate()->get()->toArray();
                    $estimates = app(RehPricing::class)->ready() ? RehCostEstimate::query()->where('unit_id', $unit->id)->lockForUpdate()->with('lines')->get()->toArray() : [];
                    $log->update(['values' => [...$log->values, 'before' => $unit->attributesToArray(), 'reviews' => $reviews, 'cost_estimates' => $estimates]]);
                    if (isset($log->values['snapshot'])) {
                        app(RehDeletionSnapshot::class)->store($log, [...app(RehDeletionSnapshot::class)->local($unit), ...array_intersect_key($log->values['snapshot'], ['remote' => true])]);
                    }
                    if (app(RehPricing::class)->ready()) {
                        RehCostEstimate::query()->where('unit_id', $unit->id)->delete();
                    }
                    $unit->reviews()->delete();
                    $unit->delete();
                } else {
                    $unit->update(['source_missing' => true, 'workflow_status' => 'source_changed', 'engineering_status' => 'pending', 'social_status' => 'pending', 'decision' => 'pending', 'version' => $unit->version + 1]);
                    $unit->reviews()->create(['user_id' => $log->user_id, 'action' => 'source_missing', 'note' => 'حُذفت الوحدة من ArcGIS بواسطة مسؤول قواعد البيانات. '.$log->reason]);
                    $log->update(['values' => [...$log->values, 'post_delete_revision' => $this->revision($unit->fresh())]]);
                }
            }
            $log->update(['status' => 'completed', 'message' => match ($log->field) {
                'database' => 'حُذفت الوحدة من قاعدة البيانات فقط؛ قد تعود عند المزامنة.',
                'arcgis' => 'حُذفت الوحدة من ArcGIS وبقيت محليًا بحالة غير موجودة في المصدر.',
                default => 'حُذفت الوحدة من ArcGIS وقاعدة البيانات.',
            }, 'values' => [...$log->values, 'local_deleted' => $log->field !== 'arcgis']]);
        });
    }
}
