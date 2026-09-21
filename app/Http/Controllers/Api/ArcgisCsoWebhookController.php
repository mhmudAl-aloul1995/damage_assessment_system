<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCsoArcgisWebhook;
use App\Models\ArcgisWebhookDelivery;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ArcgisCsoWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return $this->handleChallengeResponseCheck($request);
        }

        $startedAt = now();
        $payload = $this->payload($request);
        $delivery = $this->startDelivery($request, $payload, $startedAt);
        $secret = $this->secret();

        if ($secret === '') {
            Log::error('ArcGIS CSO webhook secret is not configured.');
            $this->finishDelivery(
                delivery: $delivery,
                status: 'failed',
                httpStatus: 503,
                startedAt: $startedAt,
                errorMessage: 'ArcGIS CSO webhook secret is not configured.',
            );

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
            $this->finishDelivery(
                delivery: $delivery,
                status: 'rejected',
                httpStatus: 401,
                startedAt: $startedAt,
                errorMessage: 'Invalid ArcGIS CSO webhook signature.',
            );

            return response()->json([
                'message' => 'Invalid ArcGIS CSO webhook signature.',
            ], 401);
        }

        Log::info('ArcGIS CSO webhook received.', [
            'name' => $payload['name'] ?? null,
            'events' => $payload['events'] ?? null,
            'has_changes_url' => filled($payload['changesUrl'] ?? $payload['changesURL'] ?? $payload['changes_url'] ?? null),
        ]);

        try {
            $summary = (new SyncCsoArcgisWebhook($payload))->handle();
        } catch (Throwable $exception) {
            Log::error('ArcGIS CSO webhook sync failed.', [
                'message' => $exception->getMessage(),
            ]);
            $this->finishDelivery(
                delivery: $delivery,
                status: 'failed',
                httpStatus: 500,
                startedAt: $startedAt,
                errorMessage: $exception->getMessage(),
            );

            return response()->json([
                'message' => 'CSO ArcGIS webhook sync failed.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        Log::info('ArcGIS CSO webhook synced.', [
            'summary' => $summary,
        ]);
        $this->finishDelivery(
            delivery: $delivery,
            status: 'success',
            httpStatus: 200,
            startedAt: $startedAt,
            summary: $summary,
        );

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

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->request->all();
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function startDelivery(Request $request, array $payload, CarbonInterface $startedAt): ?ArcgisWebhookDelivery
    {
        try {
            return ArcgisWebhookDelivery::query()->create([
                'source' => 'arcgis_cso',
                'webhook_name' => $payload['name'] ?? null,
                'event_names' => $this->eventNames($payload),
                'status' => 'received',
                'signature_present' => $request->header('x-esriHook-Signature') !== null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'changes_url' => $this->changesUrl($payload),
                'payload' => $payload,
                'started_at' => $startedAt,
            ]);
        } catch (QueryException $exception) {
            Log::warning('Could not store ArcGIS CSO webhook delivery.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, int>|null  $summary
     */
    private function finishDelivery(
        ?ArcgisWebhookDelivery $delivery,
        string $status,
        int $httpStatus,
        CarbonInterface $startedAt,
        ?array $summary = null,
        ?string $errorMessage = null
    ): void {
        if ($delivery === null) {
            return;
        }

        $finishedAt = now();

        try {
            $delivery->update([
                'status' => $status,
                'http_status' => $httpStatus,
                'summary' => $summary,
                'error_message' => $errorMessage,
                'finished_at' => $finishedAt,
                'duration_ms' => (int) round($finishedAt->diffInMilliseconds($startedAt, true)),
            ]);
        } catch (QueryException $exception) {
            Log::warning('Could not update ArcGIS CSO webhook delivery.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>|null
     */
    private function eventNames(array $payload): ?array
    {
        $events = $payload['events'] ?? null;

        if (! is_array($events)) {
            return null;
        }

        return collect($events)
            ->filter(fn (mixed $event): bool => is_scalar($event))
            ->map(fn (mixed $event): string => (string) $event)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function changesUrl(array $payload): ?string
    {
        $changesUrl = $payload['changesUrl']
            ?? $payload['changesURL']
            ?? $payload['changes_url']
            ?? null;

        return is_string($changesUrl) && trim($changesUrl) !== ''
            ? urldecode($changesUrl)
            : null;
    }
}
