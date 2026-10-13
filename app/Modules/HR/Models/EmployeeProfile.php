<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    protected $table = 'hr_employee_profiles';

    protected $fillable = ['employee_id', 'name_en', 'work_email', 'work_phone', 'birth_date', 'emergency_contact', 'archived_at'];

    protected $hidden = ['birth_date', 'emergency_contact'];

    protected function casts(): array
    {
        return ['birth_date' => 'encrypted', 'emergency_contact' => 'encrypted:array', 'archived_at' => \App\Modules\HR\Models\Casts\UtcInstant::class];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
