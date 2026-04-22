<?php

declare(strict_types=1);

namespace App\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\StoreTelegramBroadcastRequest;
use App\Jobs\SendTelegramBroadcastJob;
use App\Models\TelegramBroadcast;
use App\Models\TelegramDestination;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Yajra\DataTables\Facades\DataTables;

class TelegramBroadcastController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->can('view telegram integrations'), 403);

        return view('Committee.Telegram.Broadcasts.index', [
            'destinations' => TelegramDestination::query()->orderBy('name')->get(['id', 'name', 'scope_type']),
            'scopes' => ['system', 'organization', 'department', 'branch', 'network', 'platform'],
        ]);
    }

    public function data(): JsonResponse
    {
        abort_unless(auth()->user()->can('view telegram integrations'), 403);

        return DataTables::eloquent(
            TelegramBroadcast::query()->with('creator')->latest()
        )->toJson();
    }

    public function store(StoreTelegramBroadcastRequest $request): RedirectResponse
    {
        $broadcast = TelegramBroadcast::query()->create([
            'title' => $request->input('title'),
            'message' => $request->input('message'),
            'target_type' => $request->input('target_type'),
            'scope_type' => $request->input('scope_type'),
            'destination_ids_json' => $request->input('destination_ids'),
            'context_ids_json' => $request->input('context_ids'),
            'created_by' => auth()->id(),
            'status' => TelegramBroadcast::STATUS_PENDING,
        ]);

        SendTelegramBroadcastJob::dispatch($broadcast->id)->afterCommit();

        return redirect()->route('telegram.broadcasts.index')
            ->with('success', 'Telegram broadcast queued successfully.');
    }
}
