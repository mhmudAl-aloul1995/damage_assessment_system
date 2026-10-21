<?php

namespace Database\Factories;

use App\Modules\Reh\Models\RehPriceBook;
use Illuminate\Database\Eloquent\Factories\Factory;

class RehPriceBookFactory extends Factory
{
    protected $model = RehPriceBook::class;

    public function definition(): array
    {
        return ['name' => 'أسعار '.fake()->date(), 'exchange_rate' => 3, 'effective_date' => now()->toDateString(), 'status' => 'draft'];
    }
}
