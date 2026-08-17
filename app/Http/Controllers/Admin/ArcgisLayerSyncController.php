<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class ArcgisLayerSyncController extends Controller
{
    public function store(): JsonResponse
    {
        $exitCode = Artisan::call('sync:arcgis-layers', [
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            return response()->json([
                'message' => __('ui.arcgis_sync.failed'),
                'output' => Artisan::output(),
            ], 500);
        }

        return response()->json([
            'message' => __('ui.arcgis_sync.completed'),
            'output' => Artisan::output(),
        ]);
    }
}
