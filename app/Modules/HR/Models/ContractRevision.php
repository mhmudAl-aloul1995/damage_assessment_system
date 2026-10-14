<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use App\Modules\HR\Models\Casts\DateOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractRevision extends AppendOnlyModel
{
    protected $table = 'hr_contract_revisions';

    protected $fillable = ['contract_id', 'previous_approved_id', 'contract_type_id', 'document_id', 'request_key', 'request_fingerprint', 'kind', 'effective_on', 'starts_on', 'ends_on', 'type_snapshot', 'financial_terms', 'reason', 'created_by'];

    protected $hidden = ['financial_terms', 'reason', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['starts_on' => DateOnly::class, 'ends_on' => DateOnly::class, 'effective_on' => DateOnly::class, 'type_snapshot' => 'array', 'financial_terms' => 'encrypted:array', 'reason' => 'encrypted', 'created_by' => 'integer', 'previous_approved_id' => 'integer', 'document_id' => 'integer'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmploymentContract::class, 'contract_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContractType::class, 'contract_type_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'document_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decision(): HasOne
    {
        return $this->hasOne(ContractDecision::class, 'revision_id');
    }

    public function previousApproved(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_approved_id');
    }
}
