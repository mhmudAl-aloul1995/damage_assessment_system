<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Process;

class ArcgisLayerSyncController extends Controller
{
    public function store(): JsonResponse
    {
        $result = Process::path(base_path())->run($this->backgroundCommand());

        if (! $result->successful()) {
            return response()->json([
                'message' => __('ui.arcgis_sync.failed'),
                'output' => $result->errorOutput() ?: $result->output(),
            ], 500);
        }

        return response()->json([
            'message' => __('ui.arcgis_sync.started'),
        ], 202);
    }

    /**
     * @return array<int, string>
     */
    private function backgroundCommand(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [
                'cmd',
                '/C',
                'start /B "" "'.PHP_BINARY.'" artisan sync:arcgis-layers --force > NUL 2>&1',
            ];
        }

        return [
            'sh',
            '-c',
            escapeshellarg(PHP_BINARY).' artisan sync:arcgis-layers --force > /dev/null 2>&1 &',
        ];
    }
}
