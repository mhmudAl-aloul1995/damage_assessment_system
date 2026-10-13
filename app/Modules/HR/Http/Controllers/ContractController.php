<?php
namespace App\Modules\HR\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\HR\Exports\ContractExport;
use App\Modules\HR\Http\Requests\ContractReportRequest;
use App\Modules\HR\Http\Requests\DecideContractRequest;
use App\Modules\HR\Http\Requests\ProposeContractRevisionRequest;
use App\Modules\HR\Http\Requests\StoreContractRequest;
use App\Modules\HR\Http\Requests\StoreContractTypeRequest;
use App\Modules\HR\Models\ContractRevision;
use App\Modules\HR\Models\ContractType;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmploymentContract;
use App\Modules\HR\Services\ContractManagement;
use App\Modules\HR\Services\ContractReporting;
use App\Modules\HR\Services\EmployeeOrganizationScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
class ContractController extends Controller {
    public function index(ContractReportRequest $request, ContractReporting $report, EmployeeOrganizationScope $scope): View {
        $filters=$request->validated();
        return view('hr::contracts',[...$report->page($request->user(),$filters),'filters'=>$filters,
            'types'=>ContractType::query()->orderBy('name')->get(),
            'employees'=>$request->user()->can('hr.contracts.manage')?$scope->query($request->user(),'hr.contracts')->orderBy('employee_number')->limit(500)->get():collect()]);
    }
    public function store(StoreContractRequest $request, ContractManagement $management): RedirectResponse {
        $revision=$management->create($request->user(),Employee::query()->findOrFail($request->validated('employee_id')),$request->validated());
        return to_route('hr.contracts.show',$revision->contract_id)->with('success','حُفظ مقترح العقد؛ لا يصبح معتمداً قبل قرار مسؤول آخر.');
    }
    public function show(Request $request, EmploymentContract $contract, ContractReporting $report): View {
        Gate::authorize('view',$contract);
        $contract->load(['employee','revisions'=>fn ($query)=>$query->with(['decision.reviewer','author','document','type'])->latest('id')]);
        return view('hr::contract-show',['contract'=>$contract,'row'=>$report->row($contract),'types'=>ContractType::query()->where('active',true)->orderBy('name')->get(),
            'sensitive'=>$request->user()->can('sensitive',$contract),'latestApproved'=>$report->approved($contract)->last()]);
    }
    public function propose(ProposeContractRevisionRequest $request, EmploymentContract $contract, ContractManagement $management): RedirectResponse {
        $management->propose($request->user(),$contract,$request->validated());
        return back()->with('success','حُفظ الإصدار المقترح؛ السجل المعتمد لم يتغير.');
    }
    public function decide(DecideContractRequest $request, ContractRevision $revision, ContractManagement $management): RedirectResponse {
        $management->decide($request->user(),$revision,$request->validated());
        return back()->with('success','سُجل القرار النهائي وحُفظ تاريخ العقد.');
    }
    public function types(): View {
        Gate::authorize('hr.contracts.configure');
        return view('hr::contract-types',['types'=>ContractType::query()->orderBy('name')->paginate(25)]);
    }
    public function storeType(StoreContractTypeRequest $request, ContractManagement $management): RedirectResponse {
        $management->saveType($request->user(),$request->validated());
        return back()->with('success','حُفظ نوع العقد وإعدادات التنبيه.');
    }
    public function updateType(StoreContractTypeRequest $request, ContractType $type, ContractManagement $management): RedirectResponse {
        $management->saveType($request->user(),$request->validated(),$type->id);
        return back()->with('success','حُفظت إعدادات النوع؛ النسخ التاريخية محفوظة.');
    }
    public function export(ContractReportRequest $request, ContractReporting $report): BinaryFileResponse {
        Gate::authorize('hr.contracts.export');
        abort_if($report->query($request->user(),$request->validated())->count()>500,422,'ضيّق نطاق التقرير إلى 500 عقد أو أقل.');
        $rows=$report->rows($request->user(),$request->validated());
        $financial=$request->user()->can('hr.contracts.sensitive') || (!$request->user()->can('hr.contracts.view') && !$request->user()->can('hr.contracts.view-team') && $request->user()->can('hr.contracts.view-own'));
        return Excel::download(new ContractExport($rows,$financial),'hr-contracts.xlsx');
    }
}
