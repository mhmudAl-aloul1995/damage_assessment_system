<?php

declare(strict_types=1);

namespace App\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\StoreTelegramIntegrationRequest;
use App\Models\TelegramIntegration;
use App\Models\User;
use App\services\TelegramConnectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Yajra\DataTables\Facades\DataTables;

class TelegramIntegrationController extends Controller
{
    public function __construct(private readonly TelegramConnectionService $connectionService) {}

    public function index(): View
    {
        return view('Committee.TelegramIntegrations.index', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username_arcgis']),
            'counts' => [
                'total' => TelegramIntegration::query()->count(),
                'connected' => TelegramIntegration::query()->where('status', TelegramIntegration::STATUS_CONNECTED)->count(),
                'pending' => TelegramIntegration::query()->where('status', TelegramIntegration::STATUS_PENDING)->count(),
                'failed' => TelegramIntegration::query()->where('status', TelegramIntegration::STATUS_FAILED)->count(),
            ],
            'canManageTelegramIntegrations' => auth()->user()->can('manage telegram integrations'),
        ]);
    }

    public function data(): JsonResponse
    {
        $query = TelegramIntegration::query()
            ->with(['user:id,name,username_arcgis', 'creator:id,name', 'linkSessions'])
            ->select('telegram_integrations.*');

        return DataTables::eloquent($query)
            ->editColumn('type', fn (TelegramIntegration $integration): string => $this->typeBadge($integration->type))
            ->editColumn('status', fn (TelegramIntegration $integration): string => $this->statusBadge($integration->status))
            ->addColumn('target_name', fn (TelegramIntegration $integration): string => e($integration->telegram_title ?: $integration->telegram_username ?: '-'))
            ->addColumn('app_user', fn (TelegramIntegration $integration): string => e($integration->user?->name ?? '-'))
            ->addColumn('shareable_link', fn (TelegramIntegration $integration): string => e($this->connectionService->shareableLink($integration) ?? '-'))
            ->addColumn('actions', fn (TelegramIntegration $integration): string => $this->actionsColumn($integration))
            ->rawColumns(['type', 'status', 'actions'])
            ->toJson();
    }

    public function store(StoreTelegramIntegrationRequest $request): RedirectResponse
    {
        $this->connectionService->createIntegration($request->validated(), auth()->user());

        return redirect()
            ->route('telegram-integrations.index')
            ->with('success', __('multilingual.telegram_integrations.messages.created'));
    }

    public function refresh(TelegramIntegration $telegramIntegration): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage telegram integrations'), 403);

        $result = $this->connectionService->refreshStatus($telegramIntegration);

        return redirect()
            ->route('telegram-integrations.index')
            ->with($result['success'] ? 'success' : 'warning', $result['message']);
    }

    public function disable(TelegramIntegration $telegramIntegration): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage telegram integrations'), 403);

        $this->connectionService->disableIntegration($telegramIntegration);

        return redirect()
            ->route('telegram-integrations.index')
            ->with('success', __('multilingual.telegram_integrations.messages.disabled'));
    }

    public function destroy(TelegramIntegration $telegramIntegration): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage telegram integrations'), 403);

        $this->connectionService->deleteIntegration($telegramIntegration);

        return redirect()
            ->route('telegram-integrations.index')
            ->with('success', __('multilingual.telegram_integrations.messages.deleted'));
    }

    private function typeBadge(string $type): string
    {
        $label = $type === TelegramIntegration::TYPE_GROUP
            ? __('multilingual.telegram_integrations.types.group')
            : __('multilingual.telegram_integrations.types.user');
        $color = $type === TelegramIntegration::TYPE_GROUP ? 'warning' : 'primary';

        return '<span class="badge badge-light-'.$color.'">'.e($label).'</span>';
    }

    private function statusBadge(string $status): string
    {
        $color = match ($status) {
            TelegramIntegration::STATUS_CONNECTED => 'success',
            TelegramIntegration::STATUS_PENDING => 'info',
            TelegramIntegration::STATUS_FAILED => 'danger',
            TelegramIntegration::STATUS_DISABLED => 'secondary',
            default => 'secondary',
        };

        return '<span class="badge badge-light-'.$color.'">'.e(__('multilingual.telegram_integrations.statuses.'.$status)).'</span>';
    }

    private function actionsColumn(TelegramIntegration $integration): string
    {
        $shareableLink = $this->connectionService->shareableLink($integration);
        $actions = [];

        if ($shareableLink !== null) {
            $actions[] = '<button type="button" class="btn btn-light-primary btn-sm telegram-copy-link" data-link="'.e($shareableLink).'">'.e(__('multilingual.telegram_integrations.actions.copy_link')).'</button>';
        }

        $actions[] = '<form method="POST" action="'.route('telegram-integrations.refresh', $integration).'" class="d-inline">'
            .csrf_field()
            .'<button type="submit" class="btn btn-light-info btn-sm">'.e(__('multilingual.telegram_integrations.actions.refresh_status')).'</button>'
            .'</form>';

        if ($integration->status !== TelegramIntegration::STATUS_DISABLED) {
            $actions[] = '<form method="POST" action="'.route('telegram-integrations.disable', $integration).'" class="d-inline">'
                .csrf_field()
                .'<button type="submit" class="btn btn-light-warning btn-sm">'.e(__('multilingual.telegram_integrations.actions.disable')).'</button>'
                .'</form>';
        }

        $actions[] = '<form method="POST" action="'.route('telegram-integrations.destroy', $integration).'" class="d-inline">'
            .csrf_field()
            .method_field('DELETE')
            .'<button type="submit" class="btn btn-light-danger btn-sm" onclick="return confirm(\''.e(__('multilingual.telegram_integrations.actions.delete_confirm')).'\')">'.e(__('multilingual.telegram_integrations.actions.delete')).'</button>'
            .'</form>';

        return '<div class="d-flex justify-content-end gap-2 flex-wrap">'.implode('', $actions).'</div>';
    }
}
