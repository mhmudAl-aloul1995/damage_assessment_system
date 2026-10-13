<?php

namespace App\Modules\HR\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeStorage
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['hr_departments', 'hr_job_titles', 'hr_office_locations', 'hr_cost_centers', 'hr_employee_profiles', 'hr_employee_assignments',
            'hr_employee_assignment_endings', 'hr_department_scopes', 'hr_department_scope_endings', 'hr_employment_histories', 'hr_employee_documents', 'hr_employee_document_withdrawals'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'يلزم تثبيت تحديث إدارة الموظفين في HR على هذا السيرفر.');
        }

        return $next($request);
    }
}
