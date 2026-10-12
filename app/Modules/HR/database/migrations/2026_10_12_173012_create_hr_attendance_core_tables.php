<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->string('employee_number', 50)->unique();
            $table->string('display_name');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });
        Schema::create('hr_work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('timezone', 64);
            $table->json('working_days');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->timestamps();
        });
        Schema::create('hr_shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->foreignId('work_schedule_id')->constrained('hr_work_schedules')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'starts_on', 'ends_on'], 'hr_assignments_employee_dates');
        });
        Schema::create('hr_attendance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->foreignId('shift_assignment_id')->constrained('hr_shift_assignments')->restrictOnDelete();
            $table->date('work_date');
            $table->enum('action', ['check_in', 'check_out']);
            $table->dateTime('occurred_at');
            $table->string('source', 32);
            $table->uuid('idempotency_key')->unique();
            $table->dateTime('created_at');
            $table->unique(['employee_id', 'work_date', 'action'], 'hr_events_employee_date_action');
            $table->index(['work_date', 'employee_id']);
        });
        Schema::create('hr_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->dateTime('created_at');
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_audit_logs');
        Schema::dropIfExists('hr_attendance_events');
        Schema::dropIfExists('hr_shift_assignments');
        Schema::dropIfExists('hr_work_schedules');
        Schema::dropIfExists('hr_employees');
    }
};
