<?php

namespace App\Modules\Reh\Services;

use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehBuilding;
use App\Modules\Reh\Models\RehSyncRun;
use App\Modules\Reh\Models\RehUnit;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

class RehSyncAudit
{
    private ?bool $ready = null;

    public function __construct(private RehSurvey $survey) {}

    /** @return list<int> */
    public function record(RehUnit|RehBuilding $record, ?array $before, RehSyncRun $run): array
    {
        $this->ready ??= $record->getConnection()->getSchemaBuilder()->hasTable('reh_audit_logs');
        if (! $this->ready) {
            throw new RuntimeException(RehFieldAudit::SETUP_MESSAGE);
        }
        $after = $record->surveyData();
        $scope = $record instanceof RehUnit ? 'unit' : 'building';
        $context = ['origin' => 'sync', 'sync_run_id' => $run->id,
            'remote_editor' => $after['Editor'] ?? $after['editor'] ?? null,
            'remote_edit_date' => $after['EditDate'] ?? $after['editdate'] ?? null,
            'reference_before' => $before === null ? null : RehSurvey::reference($before), 'reference_after' => $record->old_objectid,
            'object_id_before' => $before['objectid'] ?? null, 'object_id_after' => $record->object_id];
        $changes = [];
        if ($before === null) {
            $changes['__created'] = ['event' => 'created'];
        } else {
            foreach ($this->survey->dictionary()['fields'] as $name => $definition) {
                $type = explode(' ', $definition['type'])[0];
                if ($definition['scope'] !== $scope || ! in_array($type, ['text', 'integer', 'decimal', 'date', 'dateTime', 'image', 'select_one', 'select_multiple'], true)
                    || in_array(strtolower($name), ['objectid', 'globalid', 'parentglobalid', 'instanceid', 'deviceid', 'username', 'start', 'end', 'creator', 'editor', 'creationdate', 'editdate', 'unit_selection'], true)) {
                    continue;
                }
                $old = $before[$name] ?? null;
                $new = $after[$name] ?? null;
                if ($this->comparable($old, $type) !== $this->comparable($new, $type)) {
                    $changes[$name] = ['before' => $old, 'after' => $new, 'before_present' => array_key_exists($name, $before), 'after_present' => array_key_exists($name, $after)];
                }
            }
        }
        $ids = [];
        foreach ($changes as $field => $values) {
            $ids[] = RehAuditLog::query()->create([
                'record_kind' => $scope, 'record_id' => $record->id, 'object_id' => $record->object_id, 'global_id' => $record->global_id,
                'layer_id' => (int) config($scope === 'unit' ? 'reh.units_layer' : 'reh.buildings_layer'), 'user_id' => $run->user_id,
                'field' => $field, 'sensitive' => $field !== '__created' && $this->survey->isSensitiveField($field),
                'values' => [...$context, ...$values], 'reason' => 'استقبال البيانات من ArcGIS أثناء المزامنة.', 'status' => 'completed',
                'message' => $field === '__created' ? 'استُقبل الاستبيان لأول مرة.' : 'استُقبلت الإجابة المحدّثة من ArcGIS وحُفظت محليًا.',
            ])->id;
        }

        return $ids;
    }

    private function comparable(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($type === 'select_multiple') {
            $values = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value, flags: PREG_SPLIT_NO_EMPTY);
            $values = array_unique(array_map('strval', $values));
            sort($values);

            return $values;
        }
        if (in_array($type, ['integer', 'decimal'], true) && is_numeric($value)) {
            return (float) $value;
        }
        if (in_array($type, ['date', 'dateTime'], true)) {
            try {
                $date = is_numeric($value) ? CarbonImmutable::createFromTimestampMs((int) $value)->timezone(config('app.timezone')) : CarbonImmutable::parse($value, config('app.timezone'));

                return $type === 'date' ? $date->format('Y-m-d') : $date->utc()->format('Y-m-d H:i:s.u');
            } catch (Throwable) {
                return $value;
            }
        }

        return is_scalar($value) ? (string) $value : $value;
    }
}
