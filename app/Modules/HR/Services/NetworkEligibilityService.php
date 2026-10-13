<?php

namespace App\Modules\HR\Services;

use Illuminate\Http\Request;

class NetworkEligibilityService
{
    public function verified(?Request $request = null): bool
    {
        return false;
    }

    public function blockers(): array
    {
        return ['Authenticated internal HTTPS gateway is not configured and verified.'];
    }
}
