<?php

namespace App\Modules\HR\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSecureHrApi
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(config('hr.native_require_https') && ! $request->isSecure(), 426, 'إعدادات النظام تشترط HTTPS لخدمة الحضور.');
        abort_unless(Schema::hasTable('hr_device_credentials') && Schema::hasTable('hr_attendance_challenges'), 503, 'يلزم تثبيت تحديث قاعدة بيانات HR قبل استخدام الواجهة الأصلية.');

        return $next($request);
    }
}
