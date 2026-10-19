<?php

namespace App\Modules\Reh\Services;

use App\Models\User;
use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

class RehFieldAudit
{
    public function __construct(private RehSurvey $survey, private RehArcgisClient $client, private RehImporter $importer) {}

    public function kind(RehUnit|RehBuilding $record): string
    {
        return $record instanceof RehUnit ? 'unit' : 'building';
    }

    public function revision(RehUnit|RehBuilding $record): string
    {
        return hash('sha256', json_encode([$record->source, $record->source_hash, $record->source_missing, $record->version, $record->decision, $record->updated_at?->toJSON()], JSON_THROW_ON_ERROR));
    }

    public function fields(RehUnit|RehBuilding $record, User $user): array
    {
        $fields = [];
        foreach ($this->survey->dictionary()['fields'] as $name => $field) {
            $type = explode(' ', $field['type'])[0];
            if ($field['scope'] !== $this->kind($record) || ! in_array($type, ['text', 'integer', 'decimal', 'date', 'select_one', 'select_multiple'], true)
                || in_array(strtolower($name), ['objectid', 'globalid', 'parentglobalid', 'instanceid', 'deviceid', 'username', 'start', 'end', 'unit_selection'], true)) {
                continue;
            }
            $sensitive = $this->sensitive($field, $name);
            if (! $user->can('reh.view') || ! $user->can($sensitive ? 'reh.review.social' : 'reh.review.engineering') || ($sensitive && ! $user->can('reh.sensitive'))) {
                continue;
            }
            $list = explode(' ', $field['type'], 2)[1] ?? '';
            $fields[$name] = [...$field, 'sensitive' => $sensitive, 'input_type' => $type, 'choices' => $this->survey->dictionary()['choices'][$list] ?? []];
        }

        return $fields;
    }

    private function sensitive(array $field, string $name): bool
    {
        return $field['scope'] === 'unit' && ! str_starts_with($name, 'boq_') && ! in_array($field['group']['name'] ?? '', ['group_unit_identification', 'group_unit_characteristics', 'group_unit_safety', 'group_additional_work_items', 'group_photo_documentation'], true);
    }

    public function history(RehUnit|RehBuilding $record, User $user): Collection
    {
        return RehAuditLog::query()->with('user:id,name')->where('record_kind', $this->kind($record))->where('record_id', $record->id)
            ->when(! $user->can('reh.sensitive'), fn ($query) => $query->where('sensitive', false))->latest('id')->limit(100)->get();
    }

    public function blocked(RehUnit|RehBuilding $record): ?string
    {
        if ($record->source_missing || ($record instanceof RehUnit && $record->building->source_missing)) {
            return 'السجل غير موجود في المصدر؛ تحقق بالمزامنة قبل التدقيق.';
        }
        if ($record instanceof RehUnit ? $record->decision !== 'pending' : $record->units()->where('decision', '!=', 'pending')->exists()) {
            return 'يلزم أن يعيد مسؤول المشروع فتح القرار قبل تعديل هذا السجل أو مبنى يحتوي وحدات صدر بشأنها قرار.';
        }

        return null;
    }

    public function save(RehUnit|RehBuilding $record, User $user, array $data): void
    {
        $definition = $this->fields($record, $user)[$data['field']] ?? null;
        abort_unless($definition, 403, 'لا تملك صلاحية تعديل هذا الحقل.');
        $lock = Cache::lock('reh:sync:execution', 600);
        abort_unless($lock->get(), 409, 'هناك استقبال أو حفظ جارٍ. انتظر انتهاءه ثم حاول مجددًا.');
        $log = null;
        $attempted = false;
        try {
            $record->refresh();
            abort_if($this->revision($record) !== $data['revision'], 409, 'تغيّر الملف منذ فتحه. أعد فتحه قبل الحفظ.');
            abort_if($this->blocked($record), 409, $this->blocked($record) ?? '');
            $kind = $this->kind($record);
            abort_if(RehAuditLog::query()->where('record_kind', $kind)->where('record_id', $record->id)->whereIn('status', ['pending', 'needs_review'])->exists(), 409, 'توجد محاولة حفظ غير محسومة. افتح سجل التعديلات وتحقق من نتيجتها أولًا.');
            $field = $data['field'];
            $layer = (int) config($kind === 'unit' ? 'reh.units_layer' : 'reh.buildings_layer');
            $metadata = $this->client->metadata($layer);
            $remoteField = collect($metadata['fields'])->firstWhere('name', $field);
            if (! $remoteField || ! ($remoteField['editable'] ?? false)) {
                throw ValidationException::withMessages(['field' => 'الحقل غير متاح للتحرير في طبقة ArcGIS.']);
            }
            $value = $this->normalize($data['value'], $definition, $remoteField);
            $before = $record->source[$field] ?? null;
            $remote = $this->remote($record, $layer);
            abort_unless(array_key_exists($field, $remote) && $this->same($remote[$field], $before), 409, 'تغيّرت الإجابة في ArcGIS. استقبل آخر البيانات قبل تعديلها.');
            $hasOverride = $record instanceof RehUnit && array_key_exists($field, $record->quantity_overrides ?? []);
            if ($this->same($before, $value) && ! $hasOverride) {
                throw ValidationException::withMessages(['value' => 'القيمة الجديدة مطابقة للحالية؛ لا يوجد تعديل للحفظ.']);
            }
            $log = RehAuditLog::query()->create([
                'record_kind' => $kind, 'record_id' => $record->id, 'object_id' => $record->object_id, 'global_id' => $record->global_id,
                'layer_id' => $layer, 'user_id' => $user->id, 'field' => $field, 'sensitive' => $definition['sensitive'],
                'values' => ['before' => $before, 'after' => $value, 'previous_override' => $hasOverride ? $record->quantity_overrides[$field] : null], 'reason' => $data['reason'],
            ]);
            $record->getConnection()->transaction(function () use ($record, $data, $log, &$attempted): void {
                $current = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                if ($current instanceof RehBuilding) {
                    $current->units()->lockForUpdate()->get();
                }
                abort_if($this->revision($current) !== $data['revision'], 409, 'تغيّر الملف أثناء الحفظ. أعد فتحه.');
                abort_if($this->blocked($current), 409, $this->blocked($current) ?? '');
                $attempted = true;
                try {
                    $this->client->updateField($log->layer_id, $log->object_id, $log->field, $log->values['after']);
                } catch (RehSyncException $exception) {
                    if ($exception->getCode() === 2002) {
                        $attempted = false;
                        throw $exception;
                    }
                    $actual = $this->remote($current, $log->layer_id);
                    if (! array_key_exists($log->field, $actual) || ! $this->same($actual[$log->field], $log->values['after'])) {
                        throw $exception;
                    }
                }
                $this->applyLocal($current, $log);
                $log->update(['status' => 'completed', 'message' => 'حُفظ التعديل في قاعدة البيانات وArcGIS.']);
            });
        } catch (Throwable $exception) {
            if ($log) {
                $log->update(['status' => $attempted ? 'needs_review' : 'failed', 'message' => $attempted ? 'لم يكتمل تأكيد الحفظ في المصدرين. استخدم التحقق من النتيجة قبل إعادة المحاولة.' : 'لم يُحفظ التعديل في المصدرين.']);
            }
            if ($attempted) {
                abort(502, 'لم يكتمل تأكيد الحفظ. افتح سجل التعديلات واضغط «التحقق من النتيجة». لا تعِد إرسال التعديل.');
            }
            if ($exception instanceof RehSyncException) {
                abort(502, $exception->getMessage());
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function reconcile(RehAuditLog $log, User $user): RehUnit|RehBuilding
    {
        $record = ($log->record_kind === 'unit' ? RehUnit::query() : RehBuilding::query())->findOrFail($log->record_id);
        abort_unless(isset($this->fields($record, $user)[$log->field]), 403);
        $lock = Cache::lock('reh:sync:execution', 600);
        abort_unless($lock->get(), 409, 'انتظر انتهاء الاستقبال أو الحفظ الجاري.');
        try {
            $log->refresh();
            abort_unless(in_array($log->status, ['pending', 'needs_review'], true), 409, 'هذه المحاولة محسومة بالفعل. أعد فتح الملف.');
            $actual = $this->remote($record, $log->layer_id);
            abort_unless(array_key_exists($log->field, $actual), 409, 'الحقل غير متوفر في المصدر.');
            $record->getConnection()->transaction(function () use ($record, $actual, $log): void {
                $current = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                if ($current instanceof RehBuilding) {
                    $current->units()->lockForUpdate()->get();
                }
                if ($this->same($actual[$log->field], $log->values['after'])) {
                    abort_if($this->blocked($current), 409, $this->blocked($current) ?? '');
                    abort_unless($this->same($current->source[$log->field] ?? null, $log->values['before']) || $this->same($current->source[$log->field] ?? null, $log->values['after']), 409, 'تغيّرت الإجابة المحلية؛ يلزم مراجعة الاختلاف مع المصدر.');
                    $this->applyLocal($current, $log);
                    $log->update(['status' => 'completed', 'message' => 'تم التحقق من ArcGIS وإكمال الحفظ المحلي.']);
                } elseif ($this->same($actual[$log->field], $log->values['before'])) {
                    $log->update(['status' => 'failed', 'message' => 'تم التحقق: القيمة القديمة ما زالت في ArcGIS؛ يمكن إعادة المحاولة.']);
                } else {
                    abort_unless($this->same($current->source[$log->field] ?? null, $actual[$log->field]), 409, 'تغيّرت الإجابة إلى قيمة أخرى في المصدر. استقبل آخر البيانات ثم تحقق مجددًا.');
                    $log->update(['status' => 'superseded', 'message' => 'القيمة الحالية في المصدرين متطابقة، لكنها تختلف عن هذه المحاولة؛ تم إغلاقها دون إعادة تطبيقها.']);
                }
            });

            return $record->fresh();
        } catch (RehSyncException) {
            abort(502, 'تعذر التحقق من ArcGIS؛ بقيت المحاولة غير محسومة. حاول التحقق لاحقًا.');
        } finally {
            $lock->release();
        }
    }

    private function applyLocal(RehUnit|RehBuilding $record, RehAuditLog $log): void
    {
        $source = [...$record->source, $log->field => $log->values['after']];
        if ($record instanceof RehUnit) {
            $before = app(RehScoring::class)->snapshot($record);
            $lastReviewId = $record->reviews()->max('id') ?? 0;
            $overrides = $record->quantity_overrides ?? [];
            unset($overrides[$log->field]);
            $record->update(['quantity_overrides' => $overrides]);
            $source['parentglobalid'] ??= $record->building->global_id;
            $updated = $this->importer->unit(['attributes' => $source]);
            if ($updated->version === $record->version) {
                $updated->update(['engineering_status' => 'pending', 'social_status' => 'pending', 'decision' => 'pending', 'workflow_status' => 'source_changed', 'version' => $updated->version + 1]);
            }
            $review = $updated->reviews()->where('id', '>', $lastReviewId)->where('action', 'source_changed')->first() ?? $updated->reviews()->make();
            $review->fill(['user_id' => $log->user_id, 'action' => 'field_audit', 'note' => 'تصحيح بيانات ميدانية موثق في سجل التعديلات.', 'changes' => ['audit_log_id' => $log->id, 'scoring' => ['before' => $before, 'after' => app(RehScoring::class)->snapshot($updated)]]])->save();
        } else {
            $this->importer->building(['attributes' => $source, 'geometry' => $record->geometry]);
        }
    }

    private function remote(RehUnit|RehBuilding $record, int $layer): array
    {
        $data = $this->client->request($layer.'/query', ['objectIds' => $record->object_id, 'outFields' => '*', 'returnGeometry' => 'false']);
        $attributes = count($data['features'] ?? []) === 1 ? ($data['features'][0]['attributes'] ?? []) : [];
        abort_unless((int) ($attributes['objectid'] ?? 0) === (int) $record->object_id && strtolower(trim($attributes['globalid'] ?? '', '{} ')) === strtolower(trim($record->global_id, '{} ')), 409, 'لا يطابق سجل ArcGIS معرّفات هذا الملف. أعد المزامنة.');
        if ($record instanceof RehUnit) {
            abort_unless(strtolower(trim($attributes['parentglobalid'] ?? '', '{} ')) === strtolower(trim($record->building->global_id, '{} ')), 409, 'تغيّر ارتباط الوحدة بالمبنى في المصدر. أعد المزامنة.');
        }

        return $attributes;
    }

    private function same(mixed $a, mixed $b): bool
    {
        return $a === null || $b === null ? $a === $b : (string) $a === (string) $b;
    }

    private function normalize(string $value, array $definition, array $metadata): string|int|float
    {
        $type = $definition['input_type'];
        $invalid = static function (string $message): never {
            throw ValidationException::withMessages(['value' => $message]);
        };
        if (in_array($type, ['integer', 'decimal'], true)) {
            if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > 1000000 || ($type === 'integer' && (float) $value !== floor((float) $value))) {
                $invalid('أدخل رقمًا غير سالب ضمن الحد المسموح؛ البنود العددية تقبل أعدادًا صحيحة.');
            }
            $value = $type === 'integer' ? (int) $value : (float) $value;
        } elseif ($type === 'date') {
            try {
                if (! CarbonImmutable::canBeCreatedFromFormat($value, 'Y-m-d')) {
                    $invalid('أدخل تاريخًا صحيحًا.');
                }
                $value = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC')->getTimestampMs();
            } catch (Throwable) {
                $invalid('أدخل تاريخًا صحيحًا.');
            }
        } elseif (str_starts_with($type, 'select_')) {
            $selected = $type === 'select_multiple' ? preg_split('/[ ,]+/', $value, flags: PREG_SPLIT_NO_EMPTY) : [$value];
            foreach ($selected as $option) {
                if (! array_key_exists($option, $definition['choices'])) {
                    $invalid('اختر إجابة من الخيارات المعتمدة.');
                }
            }
            $value = implode(' ', array_unique($selected));
        }
        if (($metadata['type'] ?? '') === 'esriFieldTypeString' && mb_strlen((string) $value) > ($metadata['length'] ?? 4000)) {
            $invalid('القيمة تتجاوز طول الحقل في ArcGIS.');
        }
        $domain = $metadata['domain'] ?? [];
        if (($domain['type'] ?? '') === 'codedValue' && ! in_array((string) $value, array_map('strval', array_column($domain['codedValues'] ?? [], 'code')), true)) {
            $invalid('القيمة غير مسموحة في نطاق الحقل في ArcGIS.');
        }
        if (($domain['type'] ?? '') === 'range' && ($value < $domain['range'][0] || $value > $domain['range'][1])) {
            $invalid('القيمة خارج حدود الحقل في ArcGIS.');
        }

        return $value;
    }
}
