<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentHistory extends AppendOnlyModel
{
    protected $table = 'hr_employment_histories';

    protected $fillable = ['employee_id', 'event_type', 'occurred_on', 'note', 'metadata', 'created_by'];

    protected $hidden = ['note'];

    protected function casts(): array
    {
        return ['occurred_on' => \App\Modules\HR\Models\Casts\DateOnly::class, 'note' => 'encrypted', 'metadata' => 'array'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
