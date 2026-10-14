<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\HR\Models\ContractType;
use Illuminate\Database\Seeder;

class HrContractTypesSeeder extends Seeder
{
    public function run(): void
    {
        $names = collect(['PHC', 'UNDP', 'MoPWH', 'PEF'])->merge(User::query()->whereNotNull('contract_type')->distinct()->pluck('contract_type'))->filter(fn ($name): bool => trim((string) $name) !== '')->unique();
        foreach ($names as $name) {
            ContractType::query()->firstOrCreate(['code' => 'TYPE-'.substr(hash('sha256', $name), 0, 16)], ['name' => $name, 'active' => true, 'requires_end' => false, 'reminder_days' => []]);
        }
    }
}
