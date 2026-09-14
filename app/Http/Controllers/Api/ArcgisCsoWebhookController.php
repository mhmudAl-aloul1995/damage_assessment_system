<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCsoArcgisWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ArcgisCsoWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return $this->handleChallengeResponseCheck($request);
        }

        $secret = $this->secret();

        if ($secret === '') {
            Log::error('ArcGIS CSO webhook secret is not configured.');

            return response()->json([
                'message' => 'ArcGIS CSO webhook secret is not configured.',
            ], 503);
        }

        if (! $this->hasValidSignature($request, $secret)) {
            Log::warning('ArcGIS CSO webhook rejected invalid signature.', [
                'ip' => $request->ip(),
                'has_signature' => $request->header('x-esriHook-Signature') !== null,
                'content_length' => strlen($request->getContent()),
            ]);

            return response()->json([
                'message' => 'Invalid ArcGIS CSO webhook signature.',
            ], 401);
        }

        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->request->all();
        }

        Log::info('ArcGIS CSO webhook received.', [
            'name' => $payload['name'] ?? null,
            'events' => $payload['events'] ?? null,
            'has_changes_url' => filled($payload['changesUrl'] ?? $payload['changesURL'] ?? $payload['changes_url'] ?? null),
        ]);

        $summary = (new SyncCsoArcgisWebhook($payload))->handle();

        Log::info('ArcGIS CSO webhook synced.', [
            'summary' => $summary,
        ]);

        return response()->json([
            'message' => 'CSO ArcGIS webhook synced.',
            'summary' => $summary,
        ]);
    }

    private function handleChallengeResponseCheck(Request $request): JsonResponse
    {
        $crcToken = (string) $request->query('crc_token', '');

        if ($crcToken === '') {
            return response()->json([
                'message' => 'CSO ArcGIS webhook receiver is ready.',
            ]);
        }

        $secret = $this->secret();

        if ($secret === '') {
            return response()->json([
                'message' => 'ArcGIS CSO webhook secret is not configured.',
            ], 503);
        }

        return response()->json([
            'response_token' => $this->signature($crcToken, $secret),
        ]);
    }

    private function hasValidSignature(Request $request, string $secret): bool
    {
        $providedSignature = (string) $request->header('x-esriHook-Signature', '');

        if ($providedSignature === '') {
            return false;
        }

        return hash_equals($this->signature($request->getContent(), $secret), $providedSignature);
    }

    private function signature(string $message, string $secret): string
    {
        return 'sha256='.base64_encode(hash_hmac('sha256', $message, $secret, true));
    }

    private function secret(): string
    {
        return trim((string) config('services.arcgis.cso_webhook_secret', ''));
    }
}
