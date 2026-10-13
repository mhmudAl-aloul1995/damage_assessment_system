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
        foreach (['hr_employees', 'hr_holidays', 'hr_attendance_adjustments', 'hr_attendance_daily_summaries', 'hr_assignment_retirements', 'hr_holiday_cancellations'] as $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                throw new RuntimeException('لم تصبح جداول HR جاهزة بعد الترحيل.');
            }
        }
        if (Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\HrPermissionsSeeder', '--force' => true, '--no-interaction' => true]) !== 0) {
            throw new RuntimeException('فشلت تهيئة صلاحيات HR.');
        }
        foreach (['route:clear', 'config:clear'] as $command) {
            if (Artisan::call($command, ['--no-interaction' => true]) !== 0) {
                throw new RuntimeException('لم يكتمل تحديث ذاكرة إعدادات ومسارات التطبيق.');
            }
        }
    }
}
