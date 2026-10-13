<?php

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceChallengeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'protocol' => 'PHC-HR-1', 'payload' => base64_encode($this->payload), 'expires_at' => $this->expires_at->toIso8601String()];
    }
}
