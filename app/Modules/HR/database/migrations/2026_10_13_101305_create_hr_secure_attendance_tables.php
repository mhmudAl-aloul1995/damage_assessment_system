<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employee_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->uuid('device_uuid')->unique();
            $table->string('label', 100);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('hr_device_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained('hr_employee_devices')->restrictOnDelete();
            $table->text('public_key');
            $table->char('key_fingerprint', 64)->unique();
            $table->string('algorithm', 30)->default('ECDSA_P256_SHA256_DER');
            $table->json('attestation_chain')->nullable();
            $table->string('attestation_status', 30)->default('unverified');
            $table->timestamps();
        });
        Schema::create('hr_device_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credential_id')->constrained('hr_device_credentials')->restrictOnDelete();
            $table->enum('decision', ['approved', 'rejected', 'revoked']);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamps();
            $table->index(['credential_id', 'id']);
        });
        Schema::create('hr_device_registration_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('payload');
            $table->dateTime('expires_at')->index();
            $table->foreignId('credential_id')->nullable()->unique()->constrained('hr_device_credentials')->restrictOnDelete();
            $table->char('request_fingerprint', 64)->nullable();
            $table->dateTime('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('hr_attendance_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->foreignId('credential_id')->constrained('hr_device_credentials')->restrictOnDelete();
            $table->enum('action', ['check_in', 'check_out']);
            $table->foreignId('shift_assignment_id')->nullable()->constrained('hr_shift_assignments')->restrictOnDelete();
            $table->text('payload');
            $table->dateTime('expires_at')->index();
            $table->foreignId('event_id')->nullable()->unique()->constrained('hr_attendance_events')->restrictOnDelete();
            $table->char('signature_fingerprint', 64)->nullable();
            $table->dateTime('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_challenges');
        Schema::dropIfExists('hr_device_registration_requests');
        Schema::dropIfExists('hr_device_decisions');
        Schema::dropIfExists('hr_device_credentials');
        Schema::dropIfExists('hr_employee_devices');
    }
};
