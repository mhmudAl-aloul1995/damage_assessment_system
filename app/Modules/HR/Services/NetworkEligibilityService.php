<?php

namespace App\Modules\HR\Services;

class NetworkEligibilityService
{
    public function verified(): bool
    {
        return false;
    }

    public function blockers(): array
    {
        return ['Authenticated internal HTTPS gateway is not configured and verified.'];
    }
}
