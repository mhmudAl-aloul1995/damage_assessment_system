<?php

declare(strict_types=1);

namespace App\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\UpdateTelegramSettingsRequest;
use App\services\TelegramSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TelegramSettingsController extends Controller
{
    public function __construct(private readonly TelegramSettingsService $settingsService) {}

    public function index(): View
    {
        abort_unless(auth()->user()->can('view telegram integrations'), 403);

        return view('Committee.Telegram.Settings.index', [
            'settings' => $this->settingsService->current(),
        ]);
    }

    public function update(UpdateTelegramSettingsRequest $request): RedirectResponse
    {
        $settings = $this->settingsService->current();
        $settings->fill([
            'bot_token' => $request->input('bot_token'),
            'bot_username' => $request->input('bot_username'),
            'webhook_secret' => $request->input('webhook_secret'),
            'is_enabled' => $request->boolean('is_enabled'),
            'parse_mode' => $request->input('parse_mode'),
        ])->save();

        return redirect()->route('telegram.settings.index')
            ->with('success', 'Telegram settings updated successfully.');
    }
}
