<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_attendance_report_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('filters');
            $table->json('employee_ids');
            $table->enum('state', ['queued', 'processing', 'ready', 'failed', 'expired'])->default('queued');
            $table->json('parts')->nullable();
            $table->string('artifact')->nullable();
            $table->char('artifact_hash', 64)->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->index(['actor_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_report_exports');
    }
};
