<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceDecision extends AppendOnlyModel
{
    protected $table = 'hr_device_decisions';

    protected $fillable = ['credential_id', 'decision', 'actor_id', 'reason'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
