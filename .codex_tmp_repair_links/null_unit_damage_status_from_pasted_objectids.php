<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\ArcgisAuditedUploadService;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$mode = $args[0] ?? 'dry-run';
$execute = $mode === 'execute';
$connectionMode = in_array('backup-server', $args, true) ? 'backup-server' : 'default';
$uploadTarget = in_array('--upload-target', $args, true);
$withAttachments = in_array('--with-attachments', $args, true);
$userId = 1334;
$fieldName = 'unit_damage_status';
$type = 'housing_table';
$ids = [
    1249, 2950, 3445, 3808, 4121, 4339, 5123, 5633, 5634, 5635, 5636, 5637, 5638,
    6453, 6460, 8487, 8936, 8937, 8938, 9140, 9241, 9495, 10960, 10964, 11176,
    12846, 13321, 14125, 16483, 16484, 17101, 18751, 19640, 19643, 19645, 19646,
    19647, 23134, 23191, 23442, 23559, 23869, 24003, 25347, 25400, 25851, 26005,
    26007, 26094, 26549, 26550, 26559, 26733, 26773, 26783, 26794, 27513, 27771,
    27772, 27773, 28356, 28540, 28541, 31664, 31672, 32341, 32448, 32700, 32701,
    32723, 33866, 34604, 35011, 35012, 35416, 35658, 35660, 36737, 36930, 36963,
    37167, 37196, 37463, 37464, 38492, 38493, 38538, 38539, 38540, 38541, 38542,
    38543, 38793, 38794, 39709, 39710, 39728, 40765, 41254,
];

if ($connectionMode === 'backup-server') {
    $server = require base_path('config/database_backup_server.php');

    config([
        'database.connections.audit_server' => [
            'driver' => 'mysql',
            'host' => $server['host'],
            'port' => $server['port'],
            'database' => $server['database'],
            'username' => $server['username'],
            'password' => $server['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ],
    ]);

    $connectionName = 'audit_server';
} else {
    $connectionName = config('database.default');
}

$db = DB::connection($connectionName);
$schema = Schema::connection($connectionName);

$user = $db->table('users')->where('id', $userId)->first(['id', 'name', 'name_en', 'email', 'id_no']);

if (! $user) {
    fwrite(STDERR, "User {$userId} was not found.\n");
    exit(1);
}

$units = $db->table('housing_units')
    ->whereIn('objectid', $ids)
    ->orderBy('objectid')
    ->get(['objectid', 'globalid', 'parentglobalid', $fieldName]);

$foundIds = $units->pluck('objectid')->all();
$missingIds = array_values(array_diff($ids, $foundIds));
$globalIds = $units->pluck('globalid')->all();

$latestEditIds = $db->table('edit_assessments')
    ->selectRaw('max(id) as id')
    ->where('type', $type)
    ->where('field_name', $fieldName)
    ->whereIn('global_id', $globalIds)
    ->groupBy('global_id')
    ->pluck('id')
    ->all();

$latestEdits = $db->table('edit_assessments')
    ->whereIn('id', $latestEditIds ?: [-1])
    ->get(['id', 'global_id', 'field_value'])
    ->keyBy('global_id');

$targets = $units
    ->filter(function ($unit) use ($latestEdits, $fieldName) {
        $latestEdit = $latestEdits->get($unit->globalid);

        if ($latestEdit && $latestEdit->field_value === null) {
            return false;
        }

        if (! $latestEdit && $unit->{$fieldName} === null) {
            return false;
        }

        return true;
    })
    ->values();

$summary = [
    'mode' => $execute ? 'execute' : 'dry-run',
    'connection' => $connectionName,
    'database' => $db->getDatabaseName(),
    'user' => $user,
    'requested_unique' => count($ids),
    'found_units' => $units->count(),
    'missing_count' => count($missingIds),
    'missing_ids' => $missingIds,
    'already_effectively_null' => $units->count() - $targets->count(),
    'will_update' => $targets->count(),
    'will_upload_target' => $uploadTarget,
    'target_upload_without_attachments' => ! $withAttachments,
    'objectids_to_update' => $targets->pluck('objectid')->values(),
    'sample_targets' => $targets->take(10)->values(),
];

if (! $execute) {
    if ($uploadTarget && $connectionMode === 'default') {
        $summary['target_upload_dry_run'] = $app
            ->make(ArcgisAuditedUploadService::class)
            ->uploadObjectIds([], $targets->pluck('objectid')->all(), ! $withAttachments, true);
    }

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
$insertedCache = 0;

$db->transaction(function () use (
    $db,
    $targets,
    $latestEdits,
    $userId,
    $type,
    $fieldName,
    $now,
    $cacheColumns,
    &$insertedEdits,
    &$insertedHistories,
    &$updatedCache,
    &$insertedCache
) {
    foreach ($targets as $unit) {
        $latestEdit = $latestEdits->get($unit->globalid);
        $oldValue = $latestEdit->field_value ?? $unit->{$fieldName};

        $editId = $db->table('edit_assessments')->insertGetId([
            'global_id' => $unit->globalid,
            'type' => $type,
            'field_name' => $fieldName,
            'field_value' => null,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $insertedEdits++;

        $building = $db->table('buildings')
            ->where('globalid', $unit->parentglobalid)
            ->first(['objectid', 'globalid']);

        $returnRequestId = null;
        if ($building) {
            $returnRequestId = $db->table('building_survey_archive_objects')
                ->where(function ($query) use ($building) {
                    if ($building->objectid) {
                        $query->where('building_objectid', $building->objectid);
                    }

                    if ($building->globalid) {
                        $query->orWhere('building_globalid', $building->globalid);
                    }
                })
                ->orderByDesc('archived_at')
                ->orderByDesc('id')
                ->value('return_request_id');
        }

        $db->table('assessment_edit_histories')->insert([
            'global_id' => $unit->globalid,
            'objectid' => $unit->objectid,
            'type' => $type,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => null,
            'edited_by' => $userId,
            'edit_assessment_id' => $editId,
            'return_request_id' => $returnRequestId,
            'source' => 'manual',
            'ip_address' => null,
            'user_agent' => 'bulk pasted objectid audit update',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $insertedHistories++;

        if ($cacheColumns === []) {
            continue;
        }

        $cacheRow = [
            $fieldName => null,
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

        $cacheQuery = $db->table('audited_housing_units')->where('objectid', $unit->objectid);

        if ($cacheQuery->exists()) {
            $db->table('audited_housing_units')->where('objectid', $unit->objectid)->update($cacheRow);
            $updatedCache++;

            continue;
        }

        $attributes = (array) $db->table('housing_units')->where('objectid', $unit->objectid)->first();
        foreach ($cacheColumns as $column) {
            if (! array_key_exists($column, $cacheRow) && array_key_exists($column, $attributes)) {
                $cacheRow[$column] = $attributes[$column];
            }
        }

        if (in_array('created_at', $cacheColumns, true) && empty($cacheRow['created_at'])) {
            $cacheRow['created_at'] = $now;
        }

        $db->table('audited_housing_units')->insert($cacheRow);
        $insertedCache++;
    }
});

Cache::forget('damage_dashboard.stats_version');

$targetUploadSummary = null;
if ($uploadTarget) {
    $targetUploadSummary = $app
        ->make(ArcgisAuditedUploadService::class)
        ->uploadObjectIds([], $targets->pluck('objectid')->all(), ! $withAttachments);
}

echo json_encode(array_merge($summary, [
    'inserted_edit_assessments' => $insertedEdits,
    'inserted_assessment_edit_histories' => $insertedHistories,
    'updated_audited_housing_units' => $updatedCache,
    'inserted_audited_housing_units' => $insertedCache,
    'target_upload_summary' => $targetUploadSummary,
]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;

if (is_array($targetUploadSummary) && (int) ($targetUploadSummary['errors'] ?? 0) > 0) {
    exit(2);
}
