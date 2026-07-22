<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncHeksKoboSubmission;
use App\Models\KoboRestSubmission;
use App\Modules\DamageAssessmentBorrowers\Services\KoboBorrowerSubmissionSyncService;
use App\Modules\Heks\Services\HeksKoboServiceRegistry;
use App\Modules\Heks\Services\HeksKoboSubmissionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

class KoboRestSubmissionController extends Controller
{
    public function __invoke(
        Request $request,
        string $service,
        KoboBorrowerSubmissionSyncService $borrowerSyncService,
        HeksKoboSubmissionSyncService $heksSyncService,
        HeksKoboServiceRegistry $heksServices
    ): JsonResponse {
        $configuredToken = (string) config('services.kobotoolbox.rest_service_token', '');
        $requestToken = (string) $request->header('X-Kobo-Token', '');

        if ($configuredToken === '' || ! hash_equals($configuredToken, $requestToken)) {
            return response()->json([
                'message' => 'Unauthorized Kobo REST submission.',
            ], 401);
        }

        $payload = $request->json()->all();
        $submissionUuid = Arr::get($payload, '_uuid') ?? Arr::get($payload, 'meta/instanceID');
        $heksServiceConfig = $heksServices->service($service) ?? [];

        $attributes = [
            'service_name' => $service,
            'source_project' => $heksServiceConfig['source_project'] ?? null,
            'survey_phase' => $heksServiceConfig['survey_phase'] ?? null,
            'submission_uuid' => $submissionUuid,
            'payload' => $payload,
            'source_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'received_at' => now(),
        ];

        $isHeksSubmission = $heksServices->accepts($service);

        $submission = filled($submissionUuid)
            ? KoboRestSubmission::query()->updateOrCreate([
                'service_name' => $service,
                'submission_uuid' => $submissionUuid,
            ], $attributes)
            : KoboRestSubmission::query()->create($attributes);

        if ($isHeksSubmission) {
            $submission->forceFill([
                'sync_status' => 'queued',
                'sync_error' => null,
                'synced_at' => null,
            ])->save();

            SyncHeksKoboSubmission::dispatch($submission->id)->onQueue((string) config('heks_kobo.queue', 'heks'));

            return response()->json([
                'message' => 'HEKS Kobo submission queued.',
                'id' => $submission->id,
                'sync_status' => 'queued',
            ], 202);
        }

        try {
            $sync = $heksSyncService->sync($submission) ?? $borrowerSyncService->sync(
                $submission,
                config('services.kobotoolbox.borrower_name_field'),
                config('services.kobotoolbox.borrower_field_map')
            );

            $submission->forceFill([
                'damage_assessment_borrower_id' => $sync['borrower']?->id ?? null,
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
