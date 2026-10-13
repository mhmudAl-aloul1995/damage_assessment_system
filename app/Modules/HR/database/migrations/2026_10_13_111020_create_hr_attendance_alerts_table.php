<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_attendance_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_exception_id')->constrained('hr_attendance_exceptions')->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->char('source_fingerprint', 64);
            $table->dateTime('created_at');
            $table->unique(['attendance_exception_id', 'recipient_id', 'source_fingerprint'], 'hr_alerts_source_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_alerts');
    }
};
