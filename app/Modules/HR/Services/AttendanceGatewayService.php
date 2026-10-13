<?php

namespace App\Modules\HR\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AttendanceGatewayService
{
    public function assertCaptureAllowed(Request $request): void
    {
        if (! $request->isSecure() || ! config('hr.attendance_enabled') || ! app(NetworkEligibilityService::class)->verified($request)) {
            throw new ServiceUnavailableHttpException(null, 'بوابة الحضور الداخلية لم تُهيأ وتُختبر بعد؛ التسجيل الإنتاجي معطل.');
        }
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
