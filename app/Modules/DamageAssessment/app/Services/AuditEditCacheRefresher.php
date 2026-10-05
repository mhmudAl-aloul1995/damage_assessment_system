<?php

namespace App\services;

use App\Models\EditAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditEditCacheRefresher
{
    /**
     * @param  array<int, object|array<string, mixed>>  $rows
     */
    public function refreshMany(array $rows): void
    {
        foreach ($rows as $row) {
            $this->refresh(
                (string) data_get($row, 'type'),
                (string) data_get($row, 'global_id'),
                (string) data_get($row, 'field_name'),
            );
        }
    }

    public function refresh(string $type, string $globalId, string $fieldName): void
    {
        $tables = $this->tablesForType($type);

        if ($tables === null) {
            return;
        }

        if (
            ! Schema::hasTable($tables['source'])
            || ! Schema::hasTable($tables['cache'])
            || ! Schema::hasColumn($tables['source'], $fieldName)
            || ! Schema::hasColumn($tables['cache'], $fieldName)
        ) {
            return;
        }

        $latestFieldEdit = EditAssessment::query()
            ->where('type', $type)
            ->where('global_id', $globalId)
            ->where('field_name', $fieldName)
            ->latest('id')
            ->first();

        $sourceRow = DB::table($tables['source'])
            ->where('globalid', $globalId)
            ->first();

        if (! $sourceRow && ! $latestFieldEdit) {
            return;
        }

        $cacheColumns = Schema::getColumnListing($tables['cache']);
        $updates = [
            $fieldName => $latestFieldEdit instanceof EditAssessment
                ? $latestFieldEdit->field_value
                : data_get($sourceRow, $fieldName),
        ];

        $latestAudit = EditAssessment::query()
            ->where('type', $type)
            ->where('global_id', $globalId)
            ->latest('id')
            ->first();

        if ($latestAudit instanceof EditAssessment) {
            if (in_array('is_audited', $cacheColumns, true)) {
                $updates['is_audited'] = true;
            }

            if (in_array('last_audit_user_id', $cacheColumns, true)) {
                $updates['last_audit_user_id'] = $latestAudit->user_id;
            }

            if (in_array('last_audit_at', $cacheColumns, true)) {
                $updates['last_audit_at'] = $latestAudit->updated_at;
            }
        }

        $latestStatus = EditAssessment::query()
            ->where('type', $type)
            ->where('global_id', $globalId)
            ->where('field_name', 'field_status')
            ->latest('id')
            ->first();

        if ($latestStatus instanceof EditAssessment) {
            if (in_array('last_status_user_id', $cacheColumns, true)) {
                $updates['last_status_user_id'] = $latestStatus->user_id;
            }

            if (in_array('last_status_at', $cacheColumns, true)) {
                $updates['last_status_at'] = $latestStatus->updated_at;
            }
        }

        if (in_array('updated_at', $cacheColumns, true)) {
            $updates['updated_at'] = now();
        }

        if (DB::table($tables['cache'])->where('globalid', $globalId)->exists()) {
            DB::table($tables['cache'])->where('globalid', $globalId)->update($updates);

            return;
        }

        if (! $sourceRow) {
            return;
        }

        $insert = [];

        foreach ($cacheColumns as $column) {
            if (array_key_exists($column, $updates)) {
                $insert[$column] = $updates[$column];

                continue;
            }

            if (property_exists($sourceRow, $column)) {
                $insert[$column] = $sourceRow->{$column};
            }
        }

        if (in_array('created_at', $cacheColumns, true) && ! array_key_exists('created_at', $insert)) {
            $insert['created_at'] = now();
        }

        DB::table($tables['cache'])->insert($insert);
    }

    /**
     * @return array{source: string, cache: string}|null
     */
    private function tablesForType(string $type): ?array
    {
        return match ($type) {
            'building_table' => ['source' => 'buildings', 'cache' => 'audited_buildings'],
            'housing_table' => ['source' => 'housing_units', 'cache' => 'audited_housing_units'],
            default => null,
        };
    }
}
