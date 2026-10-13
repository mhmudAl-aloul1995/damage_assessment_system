<?php

namespace App\Console\Commands;

use App\Modules\HR\Services\AttendanceInstaller;
use Illuminate\Console\Command;

class InstallHrAttendance extends Command
{
    protected $signature = 'hr:install-attendance {--skip-backup : تجاوز النسخ الاحتياطي في بيئة التطوير أو الاختبار فقط}';

    protected $description = 'تهيئة تحديث الحضور HR بعد النسخ الاحتياطي مع ترحيل جداول هذا الموديول فقط';

    public function handle(AttendanceInstaller $installer): int
    {
        try {
            $installer->install((bool) $this->option('skip-backup'));
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('اكتملت تهيئة HR. يمكن لحسابات Database Officer فتح صفحات الموديول مباشرة.');

        return self::SUCCESS;
    }
}
