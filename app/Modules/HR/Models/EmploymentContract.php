<?php
namespace App\Modules\HR\Models;
use App\Models\User;
use Database\Factories\HrEmploymentContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class EmploymentContract extends AppendOnlyModel {
    use HasFactory;
    protected $table = 'hr_contracts';
    protected $fillable = ['employee_id','number','primary','created_by'];
    protected function casts(): array { return ['employee_id'=>'integer','primary'=>'boolean']; }
    protected static function newFactory(): HrEmploymentContractFactory { return HrEmploymentContractFactory::new(); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function revisions(): HasMany { return $this->hasMany(ContractRevision::class,'contract_id'); }
}
