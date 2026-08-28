<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mode = $argv[1] ?? 'dry-run';
$execute = $mode === 'execute';
$connectionMode = $argv[2] ?? 'default';
$userId = 1334;
$fieldName = 'unit_damage_status';
$type = 'housing_table';
$ids = array_values(array_unique([
    506, 815, 3450, 3868, 5752, 3955, 2818, 3254, 3260, 3260, 3260, 3260, 3260, 3260,
    3991, 3991, 3493, 1256, 1256, 1256, 2475, 3372, 3341, 3530, 3530, 1463, 9122,
    4829, 4725, 7976, 7976, 8202, 9161, 8393, 8393, 8393, 8393, 8393, 7710, 8007,
    7672, 6813, 8042, 8090, 7801, 7889, 5526, 7536, 7536, 1654, 2873, 2873, 2873,
    2873, 5035, 5035, 5712, 4533, 7699, 7699, 7699, 6851, 11359, 11359, 7487, 7567,
    4691, 11222, 10578, 10578, 10034, 4224, 4650, 11875, 11875, 9730, 3233, 3233,
    12471, 13257, 7076, 10583, 5375, 4265, 4265, 7998, 7998, 11109, 11109, 11109,
    11109, 11109, 11109, 13435, 13435, 10554, 10554, 14296, 11897, 14398, 41255,
    41271, 41274, 41961, 42494, 42658, 43186, 43753, 43809, 44186, 44713, 45457,
    45502, 45680, 46342, 46875, 47017, 47087, 47119, 47416, 47862, 48044, 48546,
    48547, 48846, 49037, 50764, 52056, 52545, 52652, 52660, 53093, 53786, 53879,
    54002, 54311, 54322, 54826, 55859, 56992, 56993, 56994, 56995, 56996, 57010,
    57383, 57384, 57385, 57386, 58011, 58428, 59977, 60311, 63481, 63649, 65913,
    66416, 66420, 66670, 66772, 66874, 67083, 68868, 68869, 68876, 68877, 68878,
    68879, 69104, 69133, 69147, 69487, 69488, 69489, 69490, 69491, 69492, 69493,
    69494, 69495, 69496, 69497, 69506, 69507, 69508, 69510, 69845, 69846,
]));

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
    fwrite(STDERR, "User {$userId} was not found on the server.\n");
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
    'sample_targets' => $targets->take(10)->values(),
];

if (! $execute) {
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
    exit(0);
}

$cacheColumns = $schema->hasTable('audited_housing_units')
    ? $schema->getColumnListing('audited_housing_units')
    : [];
$housingColumns = $schema->getColumnListing('housing_units');

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
    $housingColumns,
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
            'user_agent' => 'bulk server audit repair',
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

echo json_encode(array_merge($summary, [
    'inserted_edit_assessments' => $insertedEdits,
    'inserted_assessment_edit_histories' => $insertedHistories,
    'updated_audited_housing_units' => $updatedCache,
    'inserted_audited_housing_units' => $insertedCache,
]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
