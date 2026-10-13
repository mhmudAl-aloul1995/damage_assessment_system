<?php
namespace Database\Factories;
use App\Modules\HR\Models\ContractType;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<ContractType> */
class HrContractTypeFactory extends Factory {
    protected $model=ContractType::class;
    public function definition(): array { return ['code'=>fake()->unique()->bothify('TYPE-####'),'name'=>fake()->word(),'active'=>true,'requires_end'=>true,'reminder_days'=>[]]; }
}
