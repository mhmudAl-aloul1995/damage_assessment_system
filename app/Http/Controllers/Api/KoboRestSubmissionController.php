<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KoboRestSubmission;
use App\Modules\DamageAssessmentBorrowers\Services\KoboBorrowerSubmissionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

class KoboRestSubmissionController extends Controller
{
    public function __invoke(Request $request, string $service, KoboBorrowerSubmissionSyncService $syncService): JsonResponse
    {
        $configuredToken = (string) config('services.kobotoolbox.rest_service_token', '');
        $requestToken = (string) $request->header('X-Kobo-Token', '');

        if ($configuredToken === '' || ! hash_equals($configuredToken, $requestToken)) {
            return response()->json([
                'message' => 'Unauthorized Kobo REST submission.',
            ], 401);
        }

        $payload = $request->json()->all();
        $submissionUuid = Arr::get($payload, '_uuid') ?? Arr::get($payload, 'meta/instanceID');

        $attributes = [
            'service_name' => $service,
            'submission_uuid' => $submissionUuid,
            'payload' => $payload,
            'source_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'received_at' => now(),
        ];

        $submission = filled($submissionUuid)
            ? KoboRestSubmission::query()->updateOrCreate(['submission_uuid' => $submissionUuid], $attributes)
            : KoboRestSubmission::query()->create($attributes);

        try {
            $sync = $syncService->sync($submission);

            $submission->forceFill([
                'damage_assessment_borrower_id' => $sync['borrower']?->id,
                'sync_status' => $sync['status'],
                'sync_error' => $sync['error'],
                'synced_at' => $sync['status'] === 'synced' ? now() : null,
            ])->save();
        } catch (Throwable $exception) {
            $submission->forceFill([
                'sync_status' => 'failed',
                'sync_error' => $exception->getMessage(),
                'synced_at' => null,
            ])->save();
        }

        return response()->json([
            'message' => 'Kobo submission received.',
            'id' => $submission->id,
            'sync_status' => $submission->sync_status,
            'borrower_id' => $submission->damage_assessment_borrower_id,
        ], $submission->wasRecentlyCreated ? 201 : 200);
    }
}
