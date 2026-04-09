<?php

namespace App\Console\Commands;

use App\Models\HousingUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Building;

class SyncArcGISHousing extends Command
{
    protected $signature = 'sync:housing';

    public function handle()
    {
        // 1. Remove the 120s time limit
        set_time_limit(0);
        $no_day = 400;
        $target_date_string = date('m-d-Y', strtotime("-" . $no_day . " days")) . ' 12:00:00 AM';
        $where_clause = "editdate >= '" . $target_date_string . "'";

        // 2. Get Token with Error Handling
        $response = Http::asForm()->post('https://www.arcgis.com/sharing/rest/generateToken', [
            'f' => 'json',
            'username' => 'Mahmoud.Alalloul',
            'password' => 'Qazxcv@464',
            'client' => 'referer',
            'referer' => 'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0',
        ]);


        $tokenData = $response->json();

        if (isset($tokenData['error'])) {
            $this->error("ArcGIS Token Error: " . $tokenData['error']['message']);
            return;
        }

        $token = $tokenData['token'] ?? null;
        if (!$token) {
            $this->error("Could not retrieve token. Check credentials.");
            return;
        }

        // 3. Setup Pagination Loop
        $serviceUrl = "https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/1/query";
        $offset = 0;
        $limit = 1000;
        $hasMore = true;

        while ($hasMore) {
            $this->info("Fetching records from offset: $offset...");

            $response = Http::get($serviceUrl, [
                "where" => $where_clause,
                'outFields' => '*',
                'f' => 'json',
                'token' => $token,
                'resultOffset' => $offset,
                'resultRecordCount' => $limit,
                'orderByFields' => 'objectid ASC'
            ]);

            $data = $response->json();

            $features = $data['features'] ?? [];

            if (empty($features)) {
                $hasMore = false;
                break;
            }

            $upsertData = [];

            foreach ($features as $feature) {
                $attributes = array_change_key_case($feature['attributes'], CASE_LOWER);

                // Convert ArcGIS Timestamps (Milliseconds to SQL Format)
                foreach (['creationdate', 'editdate'] as $dateField) {
                    if (isset($attributes[$dateField]) && is_numeric($attributes[$dateField])) {
                        $attributes[$dateField] = date('Y-m-d H:i:s', intval($attributes[$dateField] / 1000));
                    }
                }

                $upsertData[] = $attributes;
            }


            // ... inside your while loop after preparing $upsertData ...

            if (!empty($upsertData)) {
                // Determine a safe chunk size based on your column count
                // Formula: 65,535 / total_columns (approx 150) = ~430 max. 
                // Let's use 100 to be safe and fast.
                $chunks = array_chunk($upsertData, 100);

                foreach ($chunks as $chunk) {
                    // Get columns from the first record in the chunk
                    $columnsToUpdate = array_keys($chunk[0]);

                    // Remove objectid from the update list
                    $updateList = array_diff($columnsToUpdate, ['objectid', 'id']);


                    HousingUnit::upsert($chunk, ['objectid'], $updateList);
                }
            }

            // ... rest of the loop ...


            $hasMore = $data['exceededTransferLimit'] ?? false;
            $offset += $limit;
        }


        $this->info("Sync completed successfully!" . date("Y-m-d H:i:s"));
    }
}
