<?php

declare(strict_types=1);

namespace App\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\PromoteTelegramDiscoveredChatRequest;
use App\Models\TelegramDiscoveredChat;
use App\services\TelegramConnectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Yajra\DataTables\Facades\DataTables;

class TelegramDiscoveredChatController extends Controller
{
    public function __construct(private readonly TelegramConnectionService $connectionService) {}

    public function index(): View
    {
        abort_unless(auth()->user()->can('view telegram integrations'), 403);

        return view('Committee.Telegram.Discovered.index', [
            'scopes' => ['system', 'organization', 'department', 'branch', 'network', 'platform'],
        ]);
    }

    public function data(): JsonResponse
    {
        abort_unless(auth()->user()->can('view telegram integrations'), 403);

        return DataTables::eloquent($this->connectionService->discoveredChatsQuery())
            ->addColumn('destination', fn (TelegramDiscoveredChat $chat): string => e($chat->destination?->name ?? '-'))
            ->addColumn('actions', fn (TelegramDiscoveredChat $chat): string => $this->actionsColumn($chat))
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function promote(PromoteTelegramDiscoveredChatRequest $request, TelegramDiscoveredChat $telegramDiscoveredChat): RedirectResponse
    {
        $this->connectionService->promoteDiscoveredChat($telegramDiscoveredChat, $request->validated(), auth()->user());

        return redirect()->route('telegram.discovered.index')
            ->with('success', 'Discovered chat promoted to destination successfully.');
    }

    private function actionsColumn(TelegramDiscoveredChat $chat): string
    {
        if ($chat->telegram_destination_id !== null) {
            return '<span class="badge badge-light-success">Linked</span>';
        }

        return '<button type="button" class="btn btn-light-primary btn-sm promote-discovered-chat" '
            .'data-chat-id="'.$chat->id.'" '
            .'data-chat-title="'.e($chat->title ?: $chat->chat_id).'">'
            .'Promote</button>';
    }
}
