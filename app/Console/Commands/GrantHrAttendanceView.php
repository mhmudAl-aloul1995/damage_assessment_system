<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\HR\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class GrantHrAttendanceView extends Command
{
    protected $signature = 'hr:grant-attendance-view {email : البريد الإلكتروني للحساب الذي سيعرض الحضور}';

    protected $description = 'تفعيل صلاحية عرض الحضور للحساب المحدد في موديول الموارد البشرية';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('البريد الإلكتروني غير صالح.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user || ! $user->is_active) {
            $this->error('لم يتم العثور على حساب نشط بهذا البريد الإلكتروني.');

            return self::FAILURE;
        }

        $connection = $user->getConnection();
        foreach (['hr_employees', 'hr_work_schedules', 'hr_shift_assignments', 'hr_attendance_events', 'hr_audit_logs'] as $table) {
            if (! Schema::connection($connection->getName())->hasTable($table)) {
                $this->error('جداول HR غير جاهزة على قاعدة البيانات الحالية. شغّل ترحيل HR المحدد بعد النسخ الاحتياطي:');
                $this->line('php artisan migrate --path=app/Modules/HR/database/migrations --force --no-interaction');

                return self::FAILURE;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $connection->transaction(function () use ($user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $user->is_active) {
                throw new \RuntimeException('الحساب أصبح غير نشط أثناء تفعيل الصلاحية.');
            }
            $permission = Permission::findOrCreate('hr.attendance.view', 'web');
            if ($user->hasPermissionTo($permission)) {
                return;
            }
            $user->givePermissionTo($permission);
            AuditLog::query()->create([
                'actor_id' => null,
                'action' => 'attendance.view_permission_granted',
                'subject_type' => 'users',
                'subject_id' => $user->id,
                'metadata' => ['permission' => $permission->name, 'source' => 'console_command'],
                'created_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('تم تفعيل صلاحية عرض الحضور بنجاح. حدّث صفحة النظام.');

        return self::SUCCESS;
    }
}
