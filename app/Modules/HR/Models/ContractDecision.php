<?php
namespace App\Modules\HR\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractDecision extends AppendOnlyModel {
    protected $table='hr_contract_approvals';
    protected $fillable=['revision_id','reviewed_by','decision','reason'];
    protected $hidden=['reason'];
    protected function casts(): array { return ['reason'=>'encrypted']; }
    public function revision(): BelongsTo { return $this->belongsTo(ContractRevision::class,'revision_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
}
