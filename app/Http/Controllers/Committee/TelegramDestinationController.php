<?php

declare(strict_types=1);

namespace App\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\StoreTelegramDestinationRequest;
use App\Http\Requests\Committee\UpdateTelegramDestinationPreferencesRequest;
use App\Models\TelegramDestination;
use App\Models\User;
use App\services\TelegramConnectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Yajra\DataTables\Facades\DataTables;

class TelegramDestinationController extends Controller
{
    public function __construct(private readonly TelegramConnectionService $connectionService) {}

    public function index(): View
    {
        // abort_unless(auth()->user()->can('view telegram integrations'), 403);

        return view('Committee.Telegram.Destinations.index', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username_arcgis']),
            'scopes' => ['system', 'organization', 'department', 'branch', 'network', 'platform'],
        ]);
    }

    public function data(): JsonResponse
    {
        // abort_unless(auth()->user()->can('view telegram integrations'), 403);

        $query = TelegramDestination::query()
            ->with(['preferences', 'relatedModel', 'linkedByUser'])
            ->select('telegram_destinations.*');

        return DataTables::eloquent($query)
            ->addColumn('link_status', fn (TelegramDestination $destination): string => $this->statusBadge($destination->status))
            ->addColumn('shareable_link', fn (TelegramDestination $destination): string => e($this->connectionService->shareableLink($destination) ?? '-'))
            ->addColumn('actions', fn (TelegramDestination $destination): string => $this->actionsColumn($destination))
            ->rawColumns(['link_status', 'actions'])
            ->toJson();
    }

    public function store(StoreTelegramDestinationRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        if (filled($payload['user_id'] ?? null)) {
            $payload['related_model_type'] = User::class;
            $payload['related_model_id'] = (int) $payload['user_id'];
        }

        $this->connectionService->createDestination($payload, auth()->user());

        return redirect()->route('telegram.destinations.index')
            ->with('success', 'Telegram destination created successfully.');
    }

    public function show(TelegramDestination $telegramDestination): View
    {
        abort_unless(auth()->user()->can('view telegram integrations'), 403);

        return view('Committee.Telegram.Destinations.show', [
            'destination' => $telegramDestination->load(['preferences', 'linkedByUser', 'linkSessions', 'relatedModel']),
            'shareableLink' => $this->connectionService->shareableLink($telegramDestination),
        ]);
    }

    public function updatePreferences(UpdateTelegramDestinationPreferencesRequest $request, TelegramDestination $telegramDestination): RedirectResponse
    {
        $this->connectionService->updatePreferences($telegramDestination, [
            'notify_new_records' => $request->boolean('notify_new_records'),
            'notify_errors' => $request->boolean('notify_errors'),
            'notify_status_changes' => $request->boolean('notify_status_changes'),
            'notify_reports' => $request->boolean('notify_reports'),
            'notify_broadcasts' => $request->boolean('notify_broadcasts'),
        ]);

        return redirect()->route('telegram.destinations.show', $telegramDestination)
            ->with('success', 'Destination preferences updated successfully.');
    }

    public function regenerateLink(TelegramDestination $telegramDestination): RedirectResponse
    {
        $this->connectionService->regenerateLink($telegramDestination);

        return redirect()->route('telegram.destinations.show', $telegramDestination)
            ->with('success', 'Link regenerated successfully.');
    }

    public function refresh(TelegramDestination $telegramDestination): RedirectResponse
    {
        $result = $this->connectionService->refreshStatus($telegramDestination);

        return redirect()->route('telegram.destinations.show', $telegramDestination)
            ->with($result['success'] ? 'success' : 'warning', $result['message']);
    }

    public function unlink(TelegramDestination $telegramDestination): RedirectResponse
    {
        $this->connectionService->unlinkDestination($telegramDestination);

        return redirect()->route('telegram.destinations.show', $telegramDestination)
            ->with('success', 'Destination unlinked successfully.');
    }

    public function disable(TelegramDestination $telegramDestination): RedirectResponse
    {
        $this->connectionService->disableDestination($telegramDestination);

        return redirect()->route('telegram.destinations.index')
            ->with('success', 'Destination disabled successfully.');
    }

    public function destroy(TelegramDestination $telegramDestination): RedirectResponse
    {
        $this->connectionService->deleteDestination($telegramDestination);

        return redirect()->route('telegram.destinations.index')
            ->with('success', 'Destination deleted successfully.');
    }

    private function statusBadge(string $status): string
    {
        $color = match ($status) {
            TelegramDestination::STATUS_CONNECTED => 'success',
            TelegramDestination::STATUS_PENDING => 'info',
            TelegramDestination::STATUS_FAILED => 'danger',
            default => 'secondary',
        };

        return '<span class="badge badge-light-'.$color.'">'.e((string) str($status)->replace('_', ' ')->title()).'</span>';
    }

    private function actionsColumn(TelegramDestination $destination): string
    {
        return '<div class="d-flex justify-content-end gap-2 flex-wrap">'
            .'<a href="'.route('telegram.destinations.show', $destination).'" class="btn btn-light-primary btn-sm">Open</a>'
            .'</div>';
    }
}
