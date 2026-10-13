<?php
namespace App\Modules\HR\Services;
use App\Models\User;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\ContractDecision;
use App\Modules\HR\Models\ContractRevision;
use App\Modules\HR\Models\ContractType;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmployeeDocument;
use App\Modules\HR\Models\EmploymentContract;
use App\Modules\HR\Notifications\ContractNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class ContractManagement {
    public function __construct(private ContractReporting $report, private EmployeeOrganizationScope $scope) {}
    public function create(User $actor, Employee $employee, array $data): ContractRevision {
        return Employee::query()->getConnection()->transaction(function () use ($actor,$employee,$data): ContractRevision {
            $employee=Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $this->scope->forget(); Gate::forUser($actor)->authorize('createFor',[EmploymentContract::class,$employee]);
            $retry=ContractRevision::query()->with('contract')->where('request_key',$data['request_key'])->first();
            if ($retry) {
                abort_unless($retry->contract->employee_id===$employee->id && $retry->created_by===$actor->id,403);
                $this->assertRetry($actor,$retry->contract,$retry,$data);
                return $retry;
            }
            $contract=EmploymentContract::query()->create(['employee_id'=>$employee->id,'number'=>$data['number'],'primary'=>$data['primary']??true,'created_by'=>$actor->id]);
            return $this->append($actor,$contract,$data,'initial');
        });
    }
    public function propose(User $actor, EmploymentContract $contract, array $data): ContractRevision {
        return Employee::query()->getConnection()->transaction(function () use ($actor,$contract,$data): ContractRevision {
            Employee::query()->lockForUpdate()->findOrFail($contract->employee_id);
            $contract=EmploymentContract::query()->with(['employee','revisions.decision'])->findOrFail($contract->id);
            $this->scope->forget(); Gate::forUser($actor)->authorize('manage',$contract);
            $retry=ContractRevision::query()->where('request_key',$data['request_key'])->first();
            if ($retry) {
                abort_unless($retry->contract_id===$contract->id && $retry->created_by===$actor->id,403);
                $this->assertRetry($actor,$contract,$retry,$data);
                return $retry;
            }
            return $this->append($actor,$contract,$data,$data['kind']);
        });
    }
    private function append(User $actor, EmploymentContract $contract, array $data, string $kind): ContractRevision {
        if (! in_array($kind,['initial','amendment','renewal','termination'],true)) { throw ValidationException::withMessages(['kind'=>'نوع المقترح غير صالح.']); }
        $contract->loadMissing('revisions.decision');
        if ($contract->revisions->contains(fn ($revision): bool=>$revision->decision===null)) {
            throw ValidationException::withMessages(['request_key'=>'يوجد مقترح عقد بانتظار القرار.']);
        }
        $previous=$this->report->approved($contract)->last();
        if ((!$previous && $kind!=='initial') || ($previous && ($kind==='initial' || $previous->kind==='termination'))) {
            throw ValidationException::withMessages(['kind'=>'تسلسل العقد لا يسمح بهذا المقترح.']);
        }
        $normalized=$this->normalize($actor,$contract,$data,$kind,$previous);
        $this->validateDates($contract->employee,$normalized,$previous);
        $type=ContractType::query()->where('active',true)->findOrFail($normalized['contract_type_id']);
        if ($type->requires_end && $normalized['ends_on']===null) { throw ValidationException::withMessages(['ends_on'=>'نوع العقد يتطلب تاريخ نهاية.']); }
        $document=$this->document($contract,$normalized['document_id']);
        if ($document) { Gate::forUser($actor)->authorize('viewDocuments',$contract->employee); }
        $revision=$contract->revisions()->create([...$normalized,'request_key'=>$data['request_key'],'request_fingerprint'=>$this->fingerprint($contract,$normalized),
            'previous_approved_id'=>$previous?->id,'type_snapshot'=>['code'=>$type->code,'name'=>$type->name,'name_en'=>$type->name_en],
            'created_by'=>$actor->id]);
        $this->audit($actor,'contract.proposed',$revision->id,['kind'=>$kind]);
        return $revision;
    }
    private function normalize(User $actor, EmploymentContract $contract, array $data, string $kind, ?ContractRevision $previous): array {
        if (! Str::isUuid($data['request_key']??'')) { throw ValidationException::withMessages(['request_key'=>'معرف الطلب غير صالح.']); }
        $financial=$previous?->financial_terms;
        if (array_key_exists('financial_terms',$data)) {
            Gate::forUser($actor)->authorize('hr.contracts.sensitive');
            $financial=$this->financial($data['financial_terms']);
        }
        $starts=$data['starts_on']; $ends=$data['ends_on']??null;
        $effective=match ($kind) {
            'initial','renewal'=>$starts,
            'termination'=>$ends!==null?CarbonImmutable::parse($ends)->addDay()->toDateString():throw ValidationException::withMessages(['ends_on'=>'تاريخ نهاية العمل بالعقد مطلوب.']),
            default=>$data['effective_on']??throw ValidationException::withMessages(['effective_on'=>'تاريخ نفاذ التعديل مطلوب.']),
        };
        return ['kind'=>$kind,'contract_type_id'=>(int)$data['contract_type_id'],'document_id'=>empty($data['document_id'])?null:(int)$data['document_id'],
            'starts_on'=>$starts,'ends_on'=>$ends,'effective_on'=>$effective,'financial_terms'=>$financial,'reason'=>$data['reason']];
    }
    private function financial(?array $terms): ?array {
        if ($terms===null) { return null; }
        $scale=(int)($terms['currency_scale']??2); $amount=$terms['salary']??null;
        if ($scale<0 || $scale>4 || ($amount!==null && (!preg_match('/^\d{1,12}(?:\.\d{1,4})?$/D',(string)$amount) || strlen(explode('.',(string)$amount)[1]??'')>$scale))) {
            throw ValidationException::withMessages(['financial_terms.salary'=>'القيمة النقدية يجب أن تطابق المنازل العشرية المعتمدة للعملة.']);
        }
        if ($amount!==null && empty($terms['currency'])) { throw ValidationException::withMessages(['financial_terms.currency'=>'عملة الراتب مطلوبة.']); }
        if ($amount!==null) { $parts=explode('.',(string)$amount); $amount=ltrim($parts[0],'0')?:'0'; if ($scale>0) { $amount.='.'.str_pad($parts[1]??'',$scale,'0'); } }
        return ['salary'=>$amount,'currency'=>isset($terms['currency'])?strtoupper($terms['currency']):null,'currency_scale'=>$scale,
            'basis'=>$terms['basis']??null,'benefits'=>$terms['benefits']??null];
    }
    private function validateDates(Employee $employee, array $data, ?ContractRevision $previous): void {
        $start=$data['starts_on']; $end=$data['ends_on']; $effective=$data['effective_on'];
        if (!$employee->employedOn($start) || ($end!==null && (!$employee->employedOn($end) || $end<$start)) || ($employee->ends_on!==null && $end===null)) {
            throw ValidationException::withMessages(['starts_on'=>'يجب أن تقع مدة العقد ضمن فترة عمل الموظف.']);
        }
        if ($previous) {
            $today=now(config('hr.timezone'))->toDateString();
            if ($effective<=$previous->effective_on->toDateString() || $effective<$today) { throw ValidationException::withMessages(['effective_on'=>'المقترح يجب أن يكون نافذاً اليوم أو مستقبلاً وبعد آخر إصدار معتمد.']); }
            if ($data['kind']==='renewal' && ($previous->ends_on===null || $start<=$previous->ends_on->toDateString())) { throw ValidationException::withMessages(['starts_on'=>'التجديد يبدأ بعد نهاية الفترة السابقة.']); }
            if (in_array($data['kind'],['amendment','termination'],true) && ($start!==$previous->starts_on->toDateString() || ($previous->ends_on!==null && $effective>CarbonImmutable::parse($previous->ends_on)->addDay()->toDateString()))) {
                throw ValidationException::withMessages(['starts_on'=>'التعديل يحفظ بداية الفترة السابقة ويقع قبل نهايتها؛ استخدم التجديد لفترة جديدة.']);
            }
            if ($data['kind']==='amendment' && $end!==null && $end<$effective) { throw ValidationException::withMessages(['ends_on'=>'نهاية العقد تسبق نفاذ التعديل.']); }
        }
    }
    private function document(EmploymentContract $contract, ?int $id): ?EmployeeDocument {
        if ($id===null) { return null; }
        $document=EmployeeDocument::query()->with(['replacement','withdrawal'])->findOrFail($id);
        if ($document->employee_id!==$contract->employee_id || $document->withdrawal || $document->replacement || $document->category!=='employment') {
            throw ValidationException::withMessages(['document_id'=>'اختر مستند عمل حالي يخص الموظف.']);
        }
        return $document;
    }
    private function fingerprint(EmploymentContract $contract, array $data): string {
        return hash('sha256',json_encode(['employee_id'=>$contract->employee_id,'number'=>$contract->number,'primary'=>$contract->primary,...$data],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
    private function assertRetry(User $actor, EmploymentContract $contract, ContractRevision $revision, array $data): void {
        if ($revision->kind==='initial' && (($data['number']??$contract->number)!==$contract->number || (bool)($data['primary']??true)!==$contract->primary)) {
            throw ValidationException::withMessages(['request_key'=>'معرف الطلب مستخدم بمحتوى آخر.']);
        }
        $previous=$revision->previousApproved;
        $normalized=$this->normalize($actor,$contract,$data,$data['kind']??'initial',$previous);
        if (!hash_equals($revision->request_fingerprint,$this->fingerprint($contract,$normalized))) { throw ValidationException::withMessages(['request_key'=>'معرف الطلب مستخدم بمحتوى آخر.']); }
    }
    public function decide(User $actor, ContractRevision $revision, array $data): ContractDecision {
        return Employee::query()->getConnection()->transaction(function () use ($actor,$revision,$data): ContractDecision {
            $contract=$revision->contract; Employee::query()->lockForUpdate()->findOrFail($contract->employee_id);
            $contract=EmploymentContract::query()->with(['employee','revisions.decision'])->findOrFail($contract->id);
            $revision=ContractRevision::query()->with('decision')->findOrFail($revision->id);
            $this->scope->forget(); Gate::forUser($actor)->authorize('approve',$contract); abort_if($revision->created_by===$actor->id,403,'مراجعة العقد تحتاج مسؤولاً آخر.');
            if (!in_array($data['decision'],['approved','rejected'],true)) { throw ValidationException::withMessages(['decision'=>'القرار غير صالح.']); }
            if ($revision->decision) {
                if ($revision->decision->reviewed_by===$actor->id && $revision->decision->decision===$data['decision'] && $revision->decision->reason===$data['reason']) { return $revision->decision; }
                throw ValidationException::withMessages(['decision'=>'صدر قرار نهائي لهذا المقترح.']);
            }
            if ($data['decision']==='approved') {
                $previous=$this->report->approved($contract)->last();
                if ($revision->previous_approved_id!==$previous?->id) { throw ValidationException::withMessages(['decision'=>'تغير الإصدار المعتمد؛ يلزم مقترح جديد.']); }
                $this->validateDates($contract->employee,['kind'=>$revision->kind,'starts_on'=>$revision->starts_on->toDateString(),'ends_on'=>$revision->ends_on?->toDateString(),'effective_on'=>$revision->effective_on->toDateString()],$previous);
                $document=$this->document($contract,$revision->document_id);
                if ($document) { Gate::forUser($actor)->authorize('viewDocuments',$contract->employee); }
                $this->assertPrimaryCoverage($contract,$revision);
            }
            $decision=$revision->decision()->create(['reviewed_by'=>$actor->id,...$data]);
            $this->audit($actor,'contract.'.$data['decision'],$revision->id);
            $recipients=array_unique(array_filter([$contract->employee->user_id,$revision->created_by]));
            foreach (User::query()->whereKey($recipients)->where('is_active',true)->get() as $recipient) { $recipient->notify((new ContractNotification($contract->id,$data['decision']))->afterCommit()); }
            return $decision;
        });
    }
    private function assertPrimaryCoverage(EmploymentContract $contract, ContractRevision $revision): void {
        if (!$contract->primary || $revision->kind==='termination') { return; }
        $revision->setRelation('decision',new ContractDecision(['decision'=>'approved']));
        $contract->setRelation('revisions',$contract->revisions->reject(fn ($item): bool=>$item->id===$revision->id)->push($revision));
        $proposed=$this->report->periods($contract);
        $others=EmploymentContract::query()->where('employee_id',$contract->employee_id)->where('primary',true)->whereKeyNot($contract->id)->with('revisions.decision')->get();
        foreach ($others as $other) {
            foreach ($this->report->periods($other) as $existing) {
                foreach ($proposed as $period) {
                    if (($period['end']===null || $existing['start']<=$period['end']) && ($existing['end']===null || $period['start']<=$existing['end'])) {
                        throw ValidationException::withMessages(['decision'=>'يتداخل العقد الأساسي مع عقد أساسي معتمد آخر.']);
                    }
                }
            }
        }
        $revision->unsetRelation('decision');
    }
    public function saveType(User $actor, array $data, ?int $id=null): ContractType {
        abort_unless($actor->is_active,403); Gate::forUser($actor)->authorize('hr.contracts.configure');
        return ContractType::query()->getConnection()->transaction(function () use ($actor,$data,$id): ContractType {
            $type=$id?ContractType::query()->lockForUpdate()->findOrFail($id):new ContractType;
            $type->fill($data)->save();
            AuditLog::query()->create(['actor_id'=>$actor->id,'action'=>'contract.type_configured','subject_type'=>'hr_contract_types','subject_id'=>$type->id,'metadata'=>['changed_fields'=>array_keys($data)],'created_at'=>now('UTC')->format('Y-m-d H:i:s')]);
            return $type;
        });
    }
    private function audit(User $actor, string $action, int $revisionId, array $metadata=[]): void {
        AuditLog::query()->create(['actor_id'=>$actor->id,'action'=>$action,'subject_type'=>'hr_contract_revisions','subject_id'=>$revisionId,'metadata'=>$metadata,'created_at'=>now('UTC')->format('Y-m-d H:i:s')]);
    }
}
