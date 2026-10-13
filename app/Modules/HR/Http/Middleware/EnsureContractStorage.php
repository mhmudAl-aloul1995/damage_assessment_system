<?php
namespace App\Modules\HR\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
class EnsureContractStorage {
    public function handle(Request $request, Closure $next): Response {
        foreach (['hr_contract_types','hr_contracts','hr_contract_revisions','hr_contract_approvals','hr_contract_alerts','hr_employee_documents'] as $table) {
            abort_unless(Schema::hasTable($table),503,'يلزم تثبيت تحديث العقود في HR على هذا السيرفر.');
        }
        return $next($request);
    }
}
