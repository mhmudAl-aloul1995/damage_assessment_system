<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunArtisanCommandRequest;
use App\Support\ArtisanCommandCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class ArtisanCommandController extends Controller
{
    public function index(ArtisanCommandCatalog $catalog): View
    {
        return view('admin.artisan_commands.index', [
            'commands' => $catalog->commands(),
        ]);
    }

    public function store(RunArtisanCommandRequest $request, ArtisanCommandCatalog $catalog): JsonResponse
    {
        $command = (string) $request->validated('command');

        if (! $catalog->runInBackground($command)) {
            return response()->json([
                'message' => __('ui.artisan_commands.run_failed'),
            ], 422);
        }

        return response()->json([
            'message' => __('ui.artisan_commands.run_started', ['command' => $command]),
        ], 202);
    }
}
