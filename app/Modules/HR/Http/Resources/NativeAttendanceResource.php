<?php

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NativeAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_intersect_key($this->resource, array_flip([
            'date', 'status', 'schedule', 'timezone', 'check_in', 'check_out',
            'worked_minutes', 'expected_minutes', 'late_minutes', 'early_minutes', 'conflict', 'sessions', 'capture_state',
        ]));
    }
}
