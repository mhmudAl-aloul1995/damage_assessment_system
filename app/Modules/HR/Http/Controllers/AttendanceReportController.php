<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\AttendanceReportRequest;
use App\Modules\HR\Http\Requests\QueueAttendanceExportRequest;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\ReportExport;
use App\Modules\HR\Services\AttendanceExportQueue;
use App\Modules\HR\Services\AttendanceReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function index(AttendanceReportRequest $request, AttendanceReport $report): View
    {
        abort_unless(Schema::hasTable('hr_attendance_report_exports'), 503, 'يلزم استكمال تثبيت تحديث تقارير HR.');
        $filters = $request->validated();
        if (empty($filters['date']) && empty($filters['month'])) {
            $filters['month'] = now(config('hr.timezone'))->format('Y-m');
        }

        return view('hr::reports', [
            'filters' => $filters, 'metrics' => $report->metrics($request->user(), $filters),
            'exports' => ReportExport::query()->where('actor_id', $request->user()->id)->latest()->paginate(10),
            'requestId' => (string) Str::uuid(),
        ]);
    }

    public function queue(QueueAttendanceExportRequest $request, AttendanceExportQueue $exports): RedirectResponse
    {
        $exports->request($request->user(), $request->safe()->only(['date', 'month', 'employee_id']), $request->validated('idempotency_key'));

        return to_route('hr.attendance.reports')->with('success', 'أُدرج طلب التقرير في الخلفية؛ سيظهر رابط التنزيل بعد اكتماله.');
    }

    public function download(Request $request, ReportExport $export, AttendanceExportQueue $exports): StreamedResponse
    {
        abort_unless($export->actor_id === $request->user()->id, 403);
        $exports->authorized($export);
        $disk = Storage::disk('local');
        abort_unless($export->state === 'ready' && $export->artifact && $disk->exists($export->artifact), 404);
        abort_unless(hash_equals($export->artifact_hash, hash_file('sha256', $disk->path($export->artifact))), 409, 'ملف التقرير تغير؛ أنشئ تقريراً جديداً.');
        AuditLog::query()->create(['actor_id' => $request->user()->id, 'action' => 'attendance.export_downloaded', 'subject_type' => 'hr_attendance_report_exports', 'subject_key' => $export->id, 'metadata' => ['export_id' => $export->id], 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);

        return $disk->download($export->artifact, 'hr-attendance-'.$export->id.'.zip');
    }

    public function security(AttendanceReportRequest $request, AttendanceReport $report): View
    {
        Gate::authorize('hr.attendance.audit');
        $filters = $request->validated();
        if (empty($filters['date']) && empty($filters['month'])) {
            $filters['month'] = now(config('hr.timezone'))->format('Y-m');
        }
        [$from, $to] = $report->dates($filters);
        $start = \Carbon\CarbonImmutable::parse($from, config('hr.timezone'))->startOfDay()->utc()->format('Y-m-d H:i:s');
        $end = \Carbon\CarbonImmutable::parse($to, config('hr.timezone'))->endOfDay()->utc()->format('Y-m-d H:i:s');

        return view('hr::security-report', [
            'logs' => AuditLog::query()->with('actor')->where('action', 'attendance.native_request_denied')->whereBetween('created_at', [$start, $end])->latest('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
        ]);
    }
}
