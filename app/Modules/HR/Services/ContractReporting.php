<?php
namespace App\Modules\HR\Services;
use App\Models\User;
use App\Modules\HR\Models\ContractRevision;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\EmploymentContract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
class ContractReporting {
    public function __construct(private EmployeeOrganizationScope $scope) {}
    public function query(User $actor, array $filters=[]): Builder {
        abort_unless($actor->is_active,403);
        return EmploymentContract::query()->whereIn('employee_id',$this->scope->query($actor,'hr.contracts')->select('id'))
            ->when($filters['employee_id']??null,fn ($query,$id)=>$query->where('employee_id',$id))
            ->when($filters['contract_type_id']??null,fn ($query,$id)=>$query->whereHas('revisions',fn ($query)=>$query->where('contract_type_id',$id)))
            ->with(['employee','revisions'=>fn ($query)=>$query->with(['decision','type'])->orderBy('effective_on')->orderBy('id')])->orderBy('number');
    }
    /** @return Collection<int, ContractRevision> */
    public function approved(EmploymentContract $contract): Collection {
        $contract->loadMissing('revisions.decision');
        return $contract->revisions->filter(fn (ContractRevision $revision): bool=>$revision->decision?->decision==='approved')->sortBy(fn ($revision)=>$revision->effective_on->toDateString().sprintf('%020d',$revision->id))->values();
    }
    /** @return array<int, array{start:string, end:?string, revision:ContractRevision}> */
    public function periods(EmploymentContract $contract): array {
        $periods=[];
        foreach ($this->approved($contract) as $revision) {
            if (in_array($revision->kind,['amendment','termination'],true)) {
                $limit=CarbonImmutable::parse($revision->effective_on)->subDay()->toDateString();
                foreach ($periods as &$period) {
                    if ($period['end']===null || $period['end']>$limit) { $period['end']=$limit; }
                }
                unset($period);
                $periods=array_values(array_filter($periods,fn ($period): bool=>$period['end']===null || $period['end']>=$period['start']));
            }
            if ($revision->kind!=='termination') {
                $periods[]=['start'=>max($revision->starts_on->toDateString(),$revision->effective_on->toDateString()),'end'=>$revision->ends_on?->toDateString(),'revision'=>$revision];
            }
        }
        return $periods;
    }
    public function row(EmploymentContract $contract, ?string $date=null): array {
        $date??=now(config('hr.timezone'))->toDateString();
        $approved=$this->approved($contract); $periods=$this->periods($contract);
        $latest=$contract->revisions->sortByDesc('id')->first();
        $pending=$contract->revisions->first(fn ($revision): bool=>$revision->decision===null);
        if ($periods===[]) {
            return ['contract'=>$contract,'status'=>$pending?'pending':($latest?->decision?->decision??'draft'),'starts_on'=>null,'ends_on'=>null,'revision'=>$latest,'expiry_revision'=>$approved->last(),'pending'=>$pending];
        }
        $groups=[];
        foreach ($periods as $period) {
            $last=array_key_last($groups);
            if ($last!==null && ($groups[$last]['end']===null || CarbonImmutable::parse($groups[$last]['end'])->addDay()->toDateString()>=$period['start'])) {
                $groups[$last]['end']=$period['end']; $groups[$last]['periods'][]=$period;
            } else { $groups[]=['start'=>$period['start'],'end'=>$period['end'],'periods'=>[$period]]; }
        }
        $group=collect($groups)->first(fn ($group): bool=>$group['end']===null || $group['end']>=$date)??end($groups);
        $active=$date>=$group['start'] && ($group['end']===null || $date<=$group['end']);
        $selected=collect($group['periods'])->first(fn ($period): bool=>$period['start']<=$date && ($period['end']===null || $period['end']>=$date))??($date<$group['start']?$group['periods'][0]:end($group['periods']));
        $status=$active?'active':($date<$group['start']?'scheduled':'expired');
        if ($approved->last()?->kind==='termination' && $approved->last()->effective_on->toDateString()<=$date) { $status='terminated'; }
        return ['contract'=>$contract,'status'=>$status,'starts_on'=>$group['start'],'ends_on'=>$group['end'],'revision'=>$selected['revision'],'expiry_revision'=>$approved->last(),'pending'=>$pending];
    }
    public function rows(User $actor, array $filters=[]): Collection {
        return $this->query($actor,$filters)->get()->map(fn ($contract): array=>$this->row($contract,$filters['date']??null))
            ->filter(fn (array $row): bool=>empty($filters['status']) || $row['status']===$filters['status'])
            ->filter(fn (array $row): bool=>empty($filters['expires_before']) || ($row['ends_on']!==null && $row['ends_on']<=$filters['expires_before']))->values();
    }
    public function page(User $actor, array $filters=[]): array {
        $page=(int)($filters['page']??1); $rows=[]; $total=0; $counts=[];
        $this->query($actor,$filters)->reorder('id')->chunkById(100,function ($contracts) use ($filters,$page,&$rows,&$total,&$counts): void {
            foreach ($contracts as $contract) {
                $row=$this->row($contract,$filters['date']??null);
                if (!empty($filters['expires_before']) && ($row['ends_on']===null || $row['ends_on']>$filters['expires_before'])) { continue; }
                $counts[$row['status']]=($counts[$row['status']]??0)+1;
                if (!empty($filters['status']) && $row['status']!==$filters['status']) { continue; }
                if ($total>=($page-1)*25 && $total<$page*25) { $rows[]=$row; }
                $total++;
            }
        });
        return ['rows'=>new LengthAwarePaginator($rows,$total,25,$page,['path'=>request()->url(),'query'=>request()->query()]),'counts'=>$counts];
    }
}
