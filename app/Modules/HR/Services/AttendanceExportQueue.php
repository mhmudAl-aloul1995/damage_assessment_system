<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Exports\AttendanceExport;
use App\Modules\HR\Jobs\PrepareAttendanceExport;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\ReportExport;
use App\Modules\HR\Notifications\AttendanceExportReady;
use App\Modules\HR\Policies\EmployeePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use ZipArchive;

class AttendanceExportQueue
{
    public const PART_SIZE = 100;

    public function __construct(private AttendanceReport $report, private EmployeePolicy $policy) {}

    public function request(User $actor, array $filters, string $id): ReportExport
    {
        Gate::forUser($actor)->authorize('hr.attendance.export');
        abort_unless($actor->is_active && Str::isUuid($id), 403);
        $filters = array_intersect_key($filters, array_flip(['date', 'month', 'employee_id']));
        if (! empty($filters['employee_id'])) {
            $filters['employee_id'] = (int) $filters['employee_id'];
        }
        if (empty($filters['date']) && empty($filters['month'])) {
            $filters['date'] = now(config('hr.timezone'))->toDateString();
        }
        $filters = array_filter($filters, fn ($value): bool => $value !== null && $value !== '');
        ksort($filters);

        return ReportExport::query()->getConnection()->transaction(function () use ($actor, $filters, $id): ReportExport {
            User::query()->lockForUpdate()->findOrFail($actor->id);
            $prior = ReportExport::query()->find($id);
            if ($prior) {
                if ($prior->actor_id !== $actor->id || $prior->filters !== $filters) {
                    throw ValidationException::withMessages(['idempotency_key' => 'معرّف الطلب مستخدم لطلب آخر.']);
                }

                return $prior;
            }
            if (ReportExport::query()->where('actor_id', $actor->id)->whereIn('state', ['queued', 'processing'])->count() >= 3) {
                throw ValidationException::withMessages(['month' => 'انتظر اكتمال أحد طلبات التصدير الثلاثة المعلقة.']);
            }
            $ids = $this->report->query($actor, $filters)->reorder()->orderBy('id')->pluck('id')->all();
            if ($ids === []) {
                throw ValidationException::withMessages(['employee_id' => 'لا يوجد موظفون ضمن نطاق التقرير والصلاحية.']);
            }
            $export = ReportExport::query()->create([
                'id' => $id, 'actor_id' => $actor->id, 'filters' => $filters, 'employee_ids' => $ids, 'state' => 'queued',
                'parts' => [], 'expires_at' => now('UTC')->addHours(max(1, (int) config('hr.export_ttl_hours', 24)))->format('Y-m-d H:i:s'),
            ]);
            PrepareAttendanceExport::dispatch($id)->afterCommit();
            $this->audit($actor->id, 'attendance.export_requested', $id);

            return $export;
        });
    }

    public function authorized(ReportExport $export): User
    {
        $actor = $export->actor()->firstOrFail();
        abort_unless($actor->is_active && $export->expires_at->isFuture(), 403);
        Gate::forUser($actor)->authorize('hr.attendance.export');
        foreach (array_chunk($export->employee_ids, 500) as $ids) {
            abort_unless($this->policy->visibleTo($actor)->whereKey($ids)->count() === count($ids), 403, 'تغيّر نطاق صلاحية التقرير؛ أنشئ طلباً جديداً.');
        }

        return $actor;
    }

    public function part(string $id, int $index): void
    {
        $export = ReportExport::query()->findOrFail($id);
        $actor = $this->authorized($export);
        if ($export->state === 'ready') {
            return;
        }
        abort_unless(in_array($export->state, ['queued', 'processing'], true), 409);
        $ids = array_chunk($export->employee_ids, self::PART_SIZE)[$index] ?? null;
        if ($ids === null) {
            throw new RuntimeException('Invalid attendance export part.');
        }
        $path = $this->folder($export).'/part-'.($index + 1).'.xlsx';
        $existing = ($export->parts ?? [])[$index] ?? null;
        if ($existing && Storage::disk('local')->exists($path) && hash_equals($existing['file_hash'], hash_file('sha256', Storage::disk('local')->path($path)))) {
            return;
        }
        $rows = $this->report->rows($this->report->query($actor, $export->filters)->whereKey($ids)->get(), $export->filters);
        if (! Excel::store(new AttendanceExport($rows), $path, 'local')) {
            throw new RuntimeException('Unable to store the attendance export part.');
        }
        ReportExport::query()->getConnection()->transaction(function () use ($id, $index, $path, $rows): void {
            $export = ReportExport::query()->lockForUpdate()->findOrFail($id);
            if (! $export->expires_at->isFuture() || in_array($export->state, ['failed', 'expired'], true)) {
                Storage::disk('local')->delete($path);
                abort(403, 'انتهت صلاحية التقرير أثناء إعداد الجزء.');
            }
            $parts = $export->parts ?? [];
            $parts[$index] = [
                'file' => $path, 'rows' => $rows->count(), 'generated_at_utc' => now('UTC')->toIso8601String(),
                'sources_hash' => hash('sha256', $rows->pluck('source_fingerprint')->implode('|')),
                'file_hash' => hash_file('sha256', Storage::disk('local')->path($path)),
            ];
            $export->update(['parts' => $parts, 'state' => 'processing']);
        });
    }

    public function finish(string $id): void
    {
        $export = ReportExport::query()->findOrFail($id);
        $actor = $this->authorized($export);
        $disk = Storage::disk('local');
        if ($export->state === 'ready' && $disk->exists($export->artifact)) {
            return;
        }
        $parts = $export->parts ?? [];
        if (count($parts) !== count(array_chunk($export->employee_ids, self::PART_SIZE))) {
            throw new RuntimeException('Attendance export parts are incomplete.');
        }
        $folder = $this->folder($export);
        $partial = $folder.'/attendance.zip.incomplete';
        $artifact = $folder.'/attendance.zip';
        $zip = new ZipArchive;
        if ($zip->open($disk->path($partial), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the private attendance archive.');
        }
        try {
            foreach ($parts as $part) {
                if (! $disk->exists($part['file']) || ! hash_equals($part['file_hash'], hash_file('sha256', $disk->path($part['file']))) || ! $zip->addFile($disk->path($part['file']), basename($part['file']))) {
                    throw new RuntimeException('Attendance export part is missing or changed.');
                }
            }
            $manifest = ['filters' => $export->filters, 'employee_count' => count($export->employee_ids), 'parts' => array_values($parts)];
            if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))) {
                throw new RuntimeException('Unable to write the export manifest.');
            }
        } finally {
            $closed = $zip->close();
        }
        if (! $closed || ! $disk->move($partial, $artifact)) {
            throw new RuntimeException('Unable to finalize the attendance archive.');
        }
        $export->update(['state' => 'ready', 'artifact' => $artifact, 'artifact_hash' => hash_file('sha256', $disk->path($artifact))]);
        $this->audit($actor->id, 'attendance.export_ready', $export->id);
        $actor->notify(new AttendanceExportReady($export->id));
    }

    public function failed(string $id): void
    {
        $export = ReportExport::query()->find($id);
        if ($export && ! in_array($export->state, ['ready', 'failed', 'expired'], true)) {
            Storage::disk('local')->deleteDirectory($this->folder($export));
            $export->update(['state' => 'failed', 'parts' => [], 'artifact' => null, 'artifact_hash' => null]);
            $this->audit($export->actor_id, 'attendance.export_failed', $id);
        }
    }

    public function folder(ReportExport $export): string
    {
        if (! Str::isUuid($export->id)) {
            throw new RuntimeException('Invalid attendance archive identifier.');
        }

        return 'hr/attendance-exports/'.$export->id;
    }

    private function audit(int $actorId, string $action, string $id): void
    {
        AuditLog::query()->create(['actor_id' => $actorId, 'action' => $action, 'subject_type' => 'hr_attendance_report_exports', 'subject_key' => $id, 'metadata' => ['export_id' => $id], 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);
    }
}
