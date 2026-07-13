<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncHeksKoboSubmission;
use App\Models\KoboRestSubmission;
use App\Modules\Heks\Services\HeksKoboServiceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class HeksKoboWebhookController extends Controller
{
    public function __invoke(Request $request, string $service, HeksKoboServiceRegistry $services): JsonResponse
    {
        $configuredToken = (string) config('services.kobotoolbox.rest_service_token', '');
        $requestToken = (string) $request->header('X-Kobo-Token', '');

        if ($configuredToken === '' || ! hash_equals($configuredToken, $requestToken)) {
            return response()->json([
                'message' => 'Unauthorized Kobo webhook submission.',
            ], 401);
        }

        if (! $services->accepts($service)) {
            return response()->json([
                'message' => 'Unsupported HEKS Kobo service.',
            ], 422);
        }

        $payload = $request->json()->all();
        $submissionUuid = Arr::get($payload, '_uuid') ?? Arr::get($payload, 'meta/instanceID');
        $serviceName = $services->storageName($service);
        $attributes = [
            'service_name' => $serviceName,
            'submission_uuid' => $submissionUuid,
            'payload' => $payload,
            'source_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'received_at' => now(),
            'sync_status' => 'queued',
            'sync_error' => null,
        ];

        $submission = filled($submissionUuid)
            ? KoboRestSubmission::query()->updateOrCreate([
                'service_name' => $serviceName,
                'submission_uuid' => $submissionUuid,
            ], $attributes)
            : KoboRestSubmission::query()->create($attributes);

        SyncHeksKoboSubmission::dispatch($submission->id)->onQueue((string) config('heks_kobo.queue', 'heks'));

        return response()->json([
            'message' => 'HEKS Kobo submission queued.',
            'id' => $submission->id,
            'sync_status' => 'queued',
        ], 202);
    }
}
