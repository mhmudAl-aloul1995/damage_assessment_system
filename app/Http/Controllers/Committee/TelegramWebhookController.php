<?php

declare(strict_types=1);

namespace App\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\services\TelegramConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(private readonly TelegramConnectionService $connectionService) {}

    public function handle(Request $request, string $secret): JsonResponse
    {
        abort_unless(hash_equals((string) config('services.telegram.webhook_secret', ''), $secret), 403);

        $result = $this->connectionService->handleWebhookUpdate($request->all());

        return response()->json([
            'ok' => true,
            'status' => $result['status'],
        ]);
    }
}
