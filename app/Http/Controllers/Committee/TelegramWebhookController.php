<?php

declare(strict_types=1);

namespace App\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\services\TelegramConnectionService;
use App\services\TelegramSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramConnectionService $connectionService,
        private readonly TelegramSettingsService $settingsService,
    ) {}

    public function handle(Request $request, string $secret): JsonResponse
    {
        $settings = $this->settingsService->current();

        abort_unless($settings->is_enabled, 404);
        abort_unless(hash_equals((string) ($settings->webhook_secret ?: ''), $secret), 404);

        $result = $this->connectionService->handleWebhookUpdate($request->all());

        return response()->json([
            'ok' => true,
            'status' => $result['status'],
        ]);
    }
}
