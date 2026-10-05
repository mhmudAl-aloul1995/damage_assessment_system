<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncHeksKoboSubmission;
use App\Models\KoboRestSubmission;
use App\Modules\Heks\Services\HeksKoboServiceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use SimpleXMLElement;
use Throwable;

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

        $payload = $this->payload($request);

        if ($payload === []) {
            return response()->json([
                'message' => 'Kobo webhook payload is empty or unsupported.',
            ], 422);
        }

        $submissionUuid = Arr::get($payload, '_uuid') ?? Arr::get($payload, 'meta/instanceID');
        $serviceName = $services->storageName($service);
        $serviceConfig = $services->service($service) ?? [];
        $attributes = [
            'service_name' => $serviceName,
            'source_project' => $serviceConfig['source_project'] ?? null,
            'survey_phase' => $serviceConfig['survey_phase'] ?? null,
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

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload !== []) {
            return $payload;
        }

        $content = trim($request->getContent());

        if ($content === '') {
            return $request->request->all();
        }

        if (! str_starts_with($content, '<')) {
            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : [];
        }

        try {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NOCDATA);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        } catch (Throwable) {
            return [];
        }

        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $payload = $this->xmlElementToArray($xml);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string, mixed>|string
     */
    private function xmlElementToArray(SimpleXMLElement $element): array|string
    {
        $result = [];

        foreach ($element->children() as $name => $child) {
            $value = $this->xmlElementToArray($child);
            $name = (string) $name;

            if (array_key_exists($name, $result)) {
                if (! is_array($result[$name]) || ! array_is_list($result[$name])) {
                    $result[$name] = [$result[$name]];
                }

                $result[$name][] = $value;

                continue;
            }

            $result[$name] = $value;
        }

        foreach ($element->attributes() as $name => $value) {
            $result["@{$name}"] = (string) $value;
        }

        if ($result === []) {
            return trim((string) $element);
        }

        return $result;
    }
}
