<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance_adjustments', function (Blueprint $table): void {
            $table->boolean('close_open_session')->default(false);
        });
        Schema::create('hr_attendance_session_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('check_in_event_id')->unique()->constrained('hr_attendance_events')->restrictOnDelete();
            $table->foreignId('adjustment_id')->constrained('hr_attendance_adjustments')->restrictOnDelete();
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->text('note');
            $table->dateTime('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_session_closures');
        Schema::table('hr_attendance_adjustments', function (Blueprint $table): void {
            $table->dropColumn('close_open_session');
        });
    }
};
