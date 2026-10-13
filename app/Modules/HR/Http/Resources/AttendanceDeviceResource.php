<?php

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'credential_id' => $this->id, 'device_uuid' => $this->device->device_uuid, 'label' => $this->device->label,
            'status' => $this->status(), 'key_fingerprint' => $this->key_fingerprint,
            'attestation_status' => $this->attestation_status, 'registered_at' => $this->created_at->toIso8601String(),
        ];
    }
}
