<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['hr_departments', 'hr_job_titles', 'hr_office_locations', 'hr_cost_centers'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->string('name_en')->nullable();
                $table->boolean('active')->default(true);
                if ($name === 'hr_departments') {
                    $table->foreignId('parent_id')->nullable()->constrained('hr_departments')->restrictOnDelete();
                }
                if ($name === 'hr_office_locations') {
                    $table->string('timezone', 64);
                    $table->string('city')->nullable();
                }
                $table->timestamps();
            });
        }
        Schema::create('hr_employee_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('hr_employees')->restrictOnDelete();
            $table->string('name_en')->nullable();
            $table->string('work_email')->nullable();
            $table->string('work_phone', 50)->nullable();
            $table->text('birth_date')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('hr_employee_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('hr_departments')->restrictOnDelete();
            $table->foreignId('job_title_id')->constrained('hr_job_titles')->restrictOnDelete();
            $table->foreignId('office_location_id')->nullable()->constrained('hr_office_locations')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('hr_cost_centers')->restrictOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('hr_employees')->restrictOnDelete();
            $table->boolean('primary')->default(true);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->json('snapshot');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'primary', 'starts_on', 'ends_on'], 'hr_employee_assignment_dates');
        });
        Schema::create('hr_employee_assignment_endings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained('hr_employee_assignments')->restrictOnDelete();
            $table->date('ends_on');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('hr_department_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('hr_departments')->restrictOnDelete();
            $table->boolean('include_descendants')->default(false);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('hr_department_scope_endings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scope_id')->constrained('hr_department_scopes')->restrictOnDelete();
            $table->date('ends_on');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('hr_employment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->string('event_type', 50);
            $table->date('occurred_on');
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'occurred_on']);
        });
        Schema::create('hr_employee_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->foreignId('previous_document_id')->nullable()->unique()->constrained('hr_employee_documents')->restrictOnDelete();
            $table->string('category', 50);
            $table->text('title');
            $table->text('original_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->char('checksum', 64);
            $table->date('expires_on')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('hr_employee_document_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained('hr_employee_documents')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['hr_employee_document_withdrawals', 'hr_employee_documents', 'hr_employment_histories', 'hr_department_scope_endings', 'hr_department_scopes', 'hr_employee_assignment_endings', 'hr_employee_assignments', 'hr_employee_profiles', 'hr_cost_centers', 'hr_office_locations', 'hr_job_titles', 'hr_departments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
