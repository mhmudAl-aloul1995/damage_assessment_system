<?php

use App\Modules\HR\Models\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
            $table->string('subject_key', 80)->nullable();
            $table->index(['subject_type', 'subject_key']);
        });
    }

    public function down(): void
    {
        if (AuditLog::query()->whereNull('subject_id')->exists()) {
            throw new RuntimeException('HR audit history requires nullable/UUID subjects; use a forward migration.');
        }
        Schema::table('hr_audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['subject_type', 'subject_key']);
            $table->dropColumn('subject_key');
            $table->unsignedBigInteger('subject_id')->nullable(false)->change();
        });
    }
};
