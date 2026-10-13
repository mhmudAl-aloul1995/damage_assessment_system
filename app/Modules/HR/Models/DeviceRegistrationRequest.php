<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceRegistrationRequest extends Model
{
    protected $table = 'hr_device_registration_requests';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['expires_at' => \App\Modules\HR\Models\Casts\UtcInstant::class, 'consumed_at' => \App\Modules\HR\Models\Casts\UtcInstant::class, 'employee_id' => 'integer', 'requested_by' => 'integer'];
    }
}
