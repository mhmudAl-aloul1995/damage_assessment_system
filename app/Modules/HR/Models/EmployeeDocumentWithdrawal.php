<?php

namespace App\Modules\HR\Models;

class EmployeeDocumentWithdrawal extends AppendOnlyModel
{
    protected $table = 'hr_employee_document_withdrawals';

    protected $fillable = ['document_id', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['reason' => 'encrypted'];
    }
}
