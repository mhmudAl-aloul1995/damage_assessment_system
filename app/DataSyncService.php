<?php

namespace App;

use Illuminate\Support\Facades\DB;

class DataSyncService
{
    public function syncTable($tableName, $uniqueKey = 'objectid')
    {
        // 1. Pull from the external database connection
        DB::connection('external_db_connection')
            ->table($tableName)
            ->orderBy($uniqueKey)
            ->chunk(100, function ($records) use ($tableName, $uniqueKey) {

                // Convert collection to array
                $data = $records->map(fn($item) => (array) $item)->toArray();

                // Get all column names except 'id' to update them if record exists
                $columnsToUpdate = array_keys($data[0]);
                if (($key = array_search('id', $columnsToUpdate)) !== false) {
                    unset($columnsToUpdate[$key]);
                }

                // 2. Upsert into your local database
                DB::table($tableName)->upsert($data, [$uniqueKey], $columnsToUpdate);
            });
    }
}




?>