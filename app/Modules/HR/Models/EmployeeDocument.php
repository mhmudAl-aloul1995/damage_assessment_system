<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeDocument extends AppendOnlyModel
{
    protected $table = 'hr_employee_documents';

    protected $fillable = ['employee_id', 'previous_document_id', 'category', 'title', 'original_name', 'path', 'mime', 'size', 'checksum', 'expires_on', 'created_by'];

    protected $hidden = ['title', 'original_name', 'path'];

    protected function casts(): array
    {
        return ['employee_id' => 'integer', 'title' => 'encrypted', 'original_name' => 'encrypted', 'expires_on' => \App\Modules\HR\Models\Casts\DateOnly::class];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function replacement(): HasOne
    {
        return $this->hasOne(self::class, 'previous_document_id');
    }

    public function withdrawal(): HasOne
    {
        return $this->hasOne(EmployeeDocumentWithdrawal::class, 'document_id');
    }
}
