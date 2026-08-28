<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mode = $argv[1] ?? 'dry-run';
$execute = $mode === 'execute';
$since = $argv[2] ?? '2026-08-28 15:00:28';

$userId = 1334;
$type = 'housing_table';
$fieldName = 'unit_damage_status';
$damageStatuses = ['fully_damaged2', 'partially_damaged2', 'committee_review2', 'no_damaged'];
$securityObstacleValues = ['yes', 'نعم', 'unsafe'];

$db = DB::connection();
$schema = Schema::connection($db->getName());

$user = $db->table('users')->where('id', $userId)->first(['id', 'name', 'name_en', 'email', 'id_no']);

if (! $user) {
    fwrite(STDERR, "User {$userId} was not found.\n");
    exit(1);
}

$previousNullHistories = $db->table('assessment_edit_histories')
    ->where('edited_by', $userId)
    ->where('type', $type)
    ->where('field_name', $fieldName)
    ->whereNull('new_value')
    ->where('created_at', '>=', $since)
    ->orderBy('objectid')
    ->orderByDesc('id')
    ->get(['id', 'global_id', 'objectid', 'old_value', 'created_at'])
    ->unique('global_id')
    ->values();

$historyByGlobalId = $previousNullHistories->keyBy('global_id');
$historyGlobalIds = $historyByGlobalId->keys()->all();

if ($historyGlobalIds !== []) {
    $units = $db->table('audited_housing_units')
        ->whereIn('globalid', $historyGlobalIds)
        ->orderBy('objectid')
        ->get(['objectid', 'globalid', 'security_situation_unit', $fieldName]);
} else {
    $units = $db->table('audited_housing_units')
        ->whereIn($fieldName, $damageStatuses)
        ->whereIn(DB::raw('LOWER(TRIM(COALESCE(security_situation_unit, "")))'), $securityObstacleValues)
        ->orderBy('objectid')
        ->get(['objectid', 'globalid', 'security_situation_unit', $fieldName]);
}

$keepNull = collect();
$restore = collect();

foreach ($units as $unit) {
    $history = $historyByGlobalId->get($unit->globalid);
    $effectiveDamageStatus = $history?->old_value ?? $unit->{$fieldName};
    $securityValue = mb_strtolower(trim((string) $unit->security_situation_unit));
    $hasSecurityObstacle = in_array($securityValue, $securityObstacleValues, true);
    $hasSelectedDamageStatus = in_array(trim((string) $effectiveDamageStatus), $damageStatuses, true);

    $row = (object) [
        'objectid' => $unit->objectid,
        'globalid' => $unit->globalid,
        'security_situation_unit' => $unit->security_situation_unit,
        'current_damage_status' => $unit->{$fieldName},
        'old_damage_status' => $history?->old_value,
        'matched_filter' => $hasSecurityObstacle && $hasSelectedDamageStatus,
        'history_id' => $history?->id,
    ];

    if ($row->matched_filter) {
        $keepNull->push($row);

        continue;
    }

    if ($history && $history->old_value !== null && $history->old_value !== '') {
        $restore->push($row);
    }
}

$summary = [
    'mode' => $execute ? 'execute' : 'dry-run',
    'database' => $db->getDatabaseName(),
    'user' => $user,
    'since' => $since,
    'previous_null_histories' => $previousNullHistories->count(),
    'matched_filter_keep_null_count' => $keepNull->count(),
    'matched_filter_keep_null_objectids' => $keepNull->pluck('objectid')->values(),
    'restore_count' => $restore->count(),
    'restore_objectids' => $restore->pluck('objectid')->values(),
    'sample_keep_null' => $keepNull->take(10)->values(),
    'sample_restore' => $restore->take(10)->values(),
];

if (! $execute) {
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
    exit(0);
}

$cacheColumns = $schema->hasTable('audited_housing_units')
    ? $schema->getColumnListing('audited_housing_units')
    : [];
$now = now();
$insertedEdits = 0;
$insertedHistories = 0;
$updatedCache = 0;

$db->transaction(function () use (
    $db,
    $restore,
    $userId,
    $type,
    $fieldName,
    $now,
    $cacheColumns,
    &$insertedEdits,
    &$insertedHistories,
    &$updatedCache
) {
    foreach ($restore as $unit) {
        $editId = $db->table('edit_assessments')->insertGetId([
            'global_id' => $unit->globalid,
            'type' => $type,
            'field_name' => $fieldName,
            'field_value' => $unit->old_damage_status,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $insertedEdits++;

        $db->table('assessment_edit_histories')->insert([
            'global_id' => $unit->globalid,
            'objectid' => $unit->objectid,
            'type' => $type,
            'field_name' => $fieldName,
            'old_value' => $unit->current_damage_status,
            'new_value' => $unit->old_damage_status,
            'edited_by' => $userId,
            'edit_assessment_id' => $editId,
            'return_request_id' => null,
            'source' => 'manual',
            'ip_address' => null,
            'user_agent' => 'bulk security obstacle filter repair',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $insertedHistories++;

        if ($cacheColumns !== []) {
            $cacheRow = [
                $fieldName => $unit->old_damage_status,
            ];

            if (in_array('is_audited', $cacheColumns, true)) {
                $cacheRow['is_audited'] = true;
            }

            if (in_array('last_audit_user_id', $cacheColumns, true)) {
                $cacheRow['last_audit_user_id'] = $userId;
            }

            if (in_array('last_audit_at', $cacheColumns, true)) {
                $cacheRow['last_audit_at'] = $now;
            }

            if (in_array('updated_at', $cacheColumns, true)) {
                $cacheRow['updated_at'] = $now;
            }

            $db->table('audited_housing_units')
                ->where('globalid', $unit->globalid)
                ->update($cacheRow);
            $updatedCache++;
        }
    }
});

Cache::forget('damage_dashboard.stats_version');

echo json_encode(array_merge($summary, [
    'inserted_restore_edit_assessments' => $insertedEdits,
    'inserted_restore_histories' => $insertedHistories,
    'updated_restore_cache_rows' => $updatedCache,
]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
