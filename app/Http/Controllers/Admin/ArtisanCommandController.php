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
        $validated = $request->validated();
        $command = (string) $validated['command'];
        $arguments = $validated['arguments'] ?? [];
        $options = $validated['options'] ?? [];

        $run = $catalog->runInBackground($command, $arguments, $options);

        if ($run === false) {
            return response()->json([
                'message' => __('ui.artisan_commands.run_failed'),
            ], 422);
        }

        return response()->json([
            'message' => __('ui.artisan_commands.run_started', ['command' => $command]),
            'run_id' => $run['run_id'],
            'status_url' => route('admin.artisan-commands.runs.show', $run['run_id']),
            'preview' => $run['preview'],
        ], 202);
    }

    public function showRun(string $run, ArtisanCommandCatalog $catalog): JsonResponse
    {
        $status = $catalog->runStatus($run);

        abort_if($status === null, 404);

        return response()->json($status);
    }
}
