<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Http\Controllers\Controller;
use App\Models\HousingUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Building;
use DB;
use Carbon\Carbon;
use Schema;

class ArcGISController extends Controller
{


    public function __construct()
    {
        $this->middleware('role:Administrator|Manager');
    }

    public function index(Request $request)
    {
        // 1. Generate Token
        $tokenResponse = Http::asForm()->post('https://www.arcgis.com/sharing/rest/generateToken', [
            'f' => 'json',
            'username' => 'Mahmoud.Alalloul',
            'password' => 'Qazxcv@464',
            'client' => 'referer',
            'referer' => 'https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0',
        ]);

        $token = $tokenResponse->json()['token'];

        // 2. Setup Query
        $no_day = 50;
        $target_date_string = date('m-d-Y', strtotime("-" . $no_day . " days")) . ' 12:00:00 AM';
        $where_clause = "editdate >= '" . $target_date_string . "'";
        $serviceUrl = "https://services2.arcgis.com/VoOot7GfoaREFqQk/ArcGIS/rest/services/service_796c0e16447342c38cef2b67cd0bd723/FeatureServer/0/query";

        $offset = 0;
        $recordCount = 1000; // Match the server limit
        $hasMore = true;

        while ($hasMore) {
            $response = Http::get($serviceUrl, [
                "where" => $where_clause,
                'outFields' => '*',
                'f' => 'json',
                'token' => $token,
                'resultOffset' => $offset,
                'resultRecordCount' => $recordCount,
                'orderByFields' => 'objectid ASC' // Required for stable pagination
            ]);

            $data = $response->json();
            $features = $data['features'] ?? [];

            if (empty($features)) {
                $hasMore = false;
                break;
            }

            foreach ($features as $feature) {
                Building::updateOrCreate(
                    ['objectid' => $feature['attributes']['objectid']],
                    $feature['attributes']
                );
            }

            // Check if ArcGIS says there are more records
            $hasMore = $data['exceededTransferLimit'] ?? false;
            $offset += $recordCount;
        }

        return "Sync Complete!";
    }
}
