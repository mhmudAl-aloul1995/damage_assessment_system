<?php

namespace App\Modules\HR\Services;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AttendanceGatewayService
{
    public function assertSimulationAllowed(): void
    {
        if (! app()->environment(['local', 'testing'])
            || ! config('hr.attendance_enabled')
            || config('hr.network_mode') !== 'simulation') {
            throw new ServiceUnavailableHttpException(null, 'Secure attendance capture is not activated.');
        }
    }
}
