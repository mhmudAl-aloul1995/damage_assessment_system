<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\Employee;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AttendanceInstaller
{
    public function install(bool $skipBackup = false): void
    {
        if ($skipBackup && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('لا يمكن تجاوز النسخ الاحتياطي خارج بيئة التطوير والاختبار.');
        }
        $connection = Employee::query()->getConnection()->getName();
        if ($connection !== User::query()->getConnection()->getName()) {
            throw new RuntimeException('يجب أن تستخدم جداول HR والمستخدمون اتصال قاعدة البيانات نفسه.');
        }
        $saved = [];
        foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
            $saved[$key] = config('database_backup.'.$key);
            config(['database_backup.'.$key => null]);
        }
        try {
            if (! $skipBackup && Artisan::call('app:backup-database', ['--connection' => $connection, '--no-interaction' => true]) !== 0) {
                throw new RuntimeException('فشل النسخ الاحتياطي؛ لم يبدأ ترحيل HR. راجع سجل عملية النسخ الاحتياطي.');
            }
        } finally {
            foreach ($saved as $key => $value) {
                config(['database_backup.'.$key => $value]);
            }
        }
        if (Artisan::call('migrate', [
            '--database' => $connection, '--path' => ['app/Modules/HR/database/migrations'],
            '--force' => true, '--no-interaction' => true,
        ]) !== 0) {
            throw new RuntimeException('لم يكتمل ترحيل HR؛ لم تُنفّذ تهيئة الصلاحيات.');
        }
        foreach (['hr_employees', 'hr_holidays', 'hr_attendance_adjustments', 'hr_attendance_daily_summaries', 'hr_assignment_retirements', 'hr_holiday_cancellations', 'hr_device_credentials', 'hr_device_decisions', 'hr_device_registration_requests', 'hr_attendance_challenges', 'hr_attendance_report_exports', 'hr_attendance_alerts', 'hr_employee_profiles', 'hr_department_scopes', 'hr_employee_assignments', 'hr_employee_documents', 'hr_attendance_session_closures'] as $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                throw new RuntimeException('لم تصبح جداول HR جاهزة بعد الترحيل.');
            }
        }
        if (! Schema::connection($connection)->hasColumn('hr_audit_logs', 'subject_key')) {
            throw new RuntimeException('لم يصبح سجل تدقيق HR جاهزاً لمعرفات التقارير.');
        }
        if (! Schema::connection($connection)->hasTable('hr_attendance_projects') || ! Schema::connection($connection)->hasColumn('hr_shift_assignments', 'project_id')) {
            throw new RuntimeException('لم يكتمل تحديث مشاريع الحضور.');
        }
        if (Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\HrPermissionsSeeder', '--force' => true, '--no-interaction' => true]) !== 0) {
            throw new RuntimeException('فشلت تهيئة صلاحيات HR.');
        }
        if (Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\HrContractTypesSeeder', '--force' => true, '--no-interaction' => true]) !== 0) {
            throw new RuntimeException('فشلت تهيئة أنواع العقود.');
        }
        foreach (['route:clear', 'config:clear'] as $command) {
            if (Artisan::call($command, ['--no-interaction' => true]) !== 0) {
                throw new RuntimeException('لم يكتمل تحديث ذاكرة إعدادات ومسارات التطبيق.');
            }
        }
    }
}
