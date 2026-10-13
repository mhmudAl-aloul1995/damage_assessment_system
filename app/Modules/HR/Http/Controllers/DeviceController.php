<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\DecideDeviceRequest;
use App\Modules\HR\Models\DeviceCredential;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Services\DeviceEnrollment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class DeviceController extends Controller
{
    public function index(Request $request, EmployeePolicy $policy): View
    {
        Gate::authorize('hr.devices.view');
        abort_unless(Schema::hasTable('hr_device_credentials'), 503, 'يلزم تحديث قاعدة بيانات HR عبر أمر التثبيت.');
        $employeeIds = $policy->visibleTo($request->user())->select('id');

        return view('hr::devices', [
            'credentials' => DeviceCredential::query()->with(['device.employee', 'latestDecision.actor', 'decisions.actor'])
                ->whereHas('device', fn ($query) => $query->whereIn('employee_id', $employeeIds))->latest('id')->paginate(25),
        ]);
    }

    public function decide(DecideDeviceRequest $request, DeviceCredential $credential, DeviceEnrollment $enrollment): RedirectResponse
    {
        $enrollment->decide($request->user(), $credential, $request->validated());

        return back()->with('success', 'تم حفظ قرار الجهاز في سجل التدقيق.');
    }
}
