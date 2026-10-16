<?php

namespace App\Modules\HR\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AttendanceGatewayService
{
    public function assertCaptureAllowed(Request $request): void
    {
        $blockers = $this->blockers($request);
        if ($blockers !== []) {
            throw new ServiceUnavailableHttpException(null, $blockers[0]);
        }
    }

    public function blockers(Request $request): array
    {
        $blockers = [];
        if (! config('hr.attendance_enabled')) {
            $blockers[] = 'أوقف مسؤول النظام تسجيل الحضور مؤقتًا.';
        }
        if (config('hr.native_require_https') && ! $request->isSecure()) {
            $blockers[] = 'إعدادات النظام تشترط HTTPS لخدمة الحضور.';
        }

        return [...$blockers, ...app(NetworkEligibilityService::class)->blockers()];
    }

    public function assertSimulationAllowed(): void
    {
        if (! app()->environment(['local', 'testing'])
            || ! config('hr.attendance_enabled')
            || config('hr.network_mode') !== 'simulation') {
            throw new ServiceUnavailableHttpException(null, 'Secure attendance capture is not activated.');
        }
    }
}
