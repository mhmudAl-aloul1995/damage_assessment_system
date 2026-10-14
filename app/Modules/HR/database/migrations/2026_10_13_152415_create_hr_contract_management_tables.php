<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_contract_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('requires_end')->default(false);
            $table->json('reminder_days');
            $table->timestamps();
        });
        Schema::create('hr_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->string('number', 80)->unique();
            $table->boolean('primary')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('hr_contract_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained('hr_contracts')->restrictOnDelete();
            $table->foreignId('previous_approved_id')->nullable()->constrained('hr_contract_revisions')->restrictOnDelete();
            $table->foreignId('contract_type_id')->constrained('hr_contract_types')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('hr_employee_documents')->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->string('request_fingerprint', 64);
            $table->string('kind', 20);
            $table->date('effective_on');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->json('type_snapshot');
            $table->text('financial_terms')->nullable();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['contract_id', 'effective_on']);
            $table->index('ends_on');
        });
        Schema::create('hr_contract_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->unique()->constrained('hr_contract_revisions')->restrictOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('reason');
            $table->timestamps();
        });
        Schema::create('hr_contract_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('hr_contract_revisions')->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('threshold_days');
            $table->date('expires_on');
            $table->timestamp('created_at');
            $table->unique(['revision_id', 'recipient_id', 'threshold_days'], 'hr_contract_alert_unique');
        });
    }

    public function down(): void
    {
        foreach (['hr_contract_alerts', 'hr_contract_approvals', 'hr_contract_revisions', 'hr_contracts', 'hr_contract_types'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
