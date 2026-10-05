<?php

namespace App\services\BuildingDeletion;

use App\Models\BuildingDeletionAuditLog;
use App\Models\BuildingDeletionRequest;

class BuildingDeletionAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function log(BuildingDeletionRequest $request, string $step, string $status, ?string $message = null, ?array $payload = null, ?int $userId = null): void
    {
        BuildingDeletionAuditLog::query()->create([
            'request_id' => $request->id,
            'user_id' => $userId,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
        ]);
    }
}
