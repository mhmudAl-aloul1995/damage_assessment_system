<?php

namespace App\Modules\HR\Services;

use Illuminate\Http\Request;

class NetworkEligibilityService
{
    public function verified(?Request $request = null): bool
    {
        return config('hr.network_mode') === 'unrestricted';
    }

    public function blockers(): array
    {
        return $this->verified() ? [] : ['تسجيل الحضور مقيد بالشبكة الداخلية، ولم يكتمل إعداد التحقق منها.'];
    }
}
