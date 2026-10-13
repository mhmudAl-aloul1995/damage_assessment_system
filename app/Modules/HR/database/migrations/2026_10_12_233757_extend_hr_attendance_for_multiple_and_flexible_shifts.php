<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_work_schedules', function (Blueprint $table): void {
            $table->boolean('flexible')->default(false);
            $table->time('latest_start_at')->nullable();
        });
        Schema::table('hr_attendance_adjustments', function (Blueprint $table): void {
            $table->json('sessions')->nullable();
        });
        Schema::table('hr_attendance_events', function (Blueprint $table): void {
            $table->unique(['employee_id', 'shift_assignment_id', 'work_date', 'action'], 'hr_events_employee_shift_date_action');
            $table->dropUnique('hr_events_employee_date_action');
        });
        Schema::create('hr_assignment_retirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_assignment_id')->constrained('hr_shift_assignments')->restrictOnDelete();
            $table->date('ends_on');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('hr_holiday_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('holiday_id')->unique()->constrained('hr_holidays')->restrictOnDelete();
            $table->date('cancelled_from');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_holiday_cancellations');
        Schema::dropIfExists('hr_assignment_retirements');
        Schema::table('hr_attendance_events', function (Blueprint $table): void {
            $table->unique(['employee_id', 'work_date', 'action'], 'hr_events_employee_date_action');
            $table->dropUnique('hr_events_employee_shift_date_action');
        });
        Schema::table('hr_attendance_adjustments', function (Blueprint $table): void {
            $table->dropColumn('sessions');
        });
        Schema::table('hr_work_schedules', function (Blueprint $table): void {
            $table->dropColumn(['flexible', 'latest_start_at']);
        });
    }
};
