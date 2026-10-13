<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeviceCredential extends AppendOnlyModel
{
    protected $table = 'hr_device_credentials';

    protected $fillable = ['device_id', 'public_key', 'key_fingerprint', 'algorithm', 'attestation_chain', 'attestation_status'];

    protected $hidden = ['public_key', 'attestation_chain'];

    protected function casts(): array
    {
        return ['attestation_chain' => 'array', 'device_id' => 'integer'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(EmployeeDevice::class, 'device_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(DeviceDecision::class, 'credential_id');
    }

    public function latestDecision(): HasOne
    {
        return $this->hasOne(DeviceDecision::class, 'credential_id')->latestOfMany();
    }

    public function status(): string
    {
        return $this->latestDecision?->decision ?? 'pending';
    }
}
