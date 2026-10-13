<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('hr_holidays', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('work_schedule_id')->nullable()->constrained('hr_work_schedules')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });
        Schema::create('hr_schedule_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->date('work_date');
            $table->enum('kind', ['work_schedule', 'leave', 'excused_absence', 'off_day']);
            $table->foreignId('work_schedule_id')->nullable()->constrained('hr_work_schedules')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'work_date']);
        });
        Schema::create('hr_attendance_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->date('work_date');
            $table->enum('requested_status', ['present', 'absent', 'excused']);
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->string('timezone', 64);
            $table->text('reason');
            $table->string('basis_fingerprint', 64);
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'work_date']);
        });
        Schema::create('hr_adjustment_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adjustment_id')->unique()->constrained('hr_attendance_adjustments')->restrictOnDelete();
            $table->enum('decision', ['approved', 'rejected']);
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->text('note');
            $table->dateTime('reviewed_at');
            $table->timestamps();
        });
        Schema::create('hr_attendance_daily_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->date('work_date');
            $table->string('status', 40);
            $table->unsignedInteger('expected_minutes')->nullable();
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->unsignedInteger('late_minutes')->nullable();
            $table->unsignedInteger('early_minutes')->nullable();
            $table->unsignedInteger('overtime_minutes')->nullable();
            $table->string('source_fingerprint', 64);
            $table->string('rules_version', 32);
            $table->json('payload');
            $table->dateTime('reconciled_at');
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });
        Schema::create('hr_attendance_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_summary_id')->constrained('hr_attendance_daily_summaries')->restrictOnDelete();
            $table->string('kind', 40);
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['daily_summary_id', 'kind']);
            $table->index(['status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_exceptions');
        Schema::dropIfExists('hr_attendance_daily_summaries');
        Schema::dropIfExists('hr_adjustment_decisions');
        Schema::dropIfExists('hr_attendance_adjustments');
        Schema::dropIfExists('hr_schedule_exceptions');
        Schema::dropIfExists('hr_holidays');
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supervisor_user_id');
        });
    }
};
