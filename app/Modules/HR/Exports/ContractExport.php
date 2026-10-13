<?php
namespace App\Modules\HR\Exports;
use Illuminate\Support\Collection;
class ContractExport extends AttendanceExport {
    public function __construct(private Collection $contractRows, private bool $financial) { parent::__construct(collect()); }
    public function headings(): array { return ['رقم الموظف','الاسم','رقم العقد','نوع العقد','الحالة','من','إلى','مقترح معلق',...($this->financial?['الراتب','العملة','أساس الراتب','المزايا']:[])]; }
    public function collection(): Collection {
        return $this->contractRows->map(function (array $row): array {
            $contract=$row['contract']; $revision=$row['revision'];
            $result=[$contract->employee->employee_number,$contract->employee->display_name,$contract->number,$revision?->type_snapshot['name']??'',__('hr::contracts.statuses.'.$row['status']),$row['starts_on'],$row['ends_on'],$row['pending']?'نعم':'لا'];
            if ($this->financial) { $terms=$revision?->financial_terms; $result=[...$result,$terms['salary']??null,$terms['currency']??null,$terms['basis']??null,$terms['benefits']??null]; }
            return $result;
        });
    }
}
