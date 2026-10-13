<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeDevice extends AppendOnlyModel
{
    protected $table = 'hr_employee_devices';

    protected $fillable = ['employee_id', 'device_uuid', 'label', 'requested_by'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'requested_by' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function credential(): HasOne
    {
        return $this->hasOne(DeviceCredential::class, 'device_id');
    }
}
