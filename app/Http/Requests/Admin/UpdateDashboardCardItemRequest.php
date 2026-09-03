<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateDashboardCardItemRequest extends StoreDashboardCardItemRequest
{
    public function rules(): array
    {
        $dashboardCard = $this->route('dashboardCard');
        $dashboardCardItem = $this->route('dashboardCardItem');

        return [
            ...parent::rules(),
            'key' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('dashboard_card_items', 'key')
                    ->where('dashboard_card_id', $dashboardCard?->id)
                    ->ignore($dashboardCardItem),
            ],
        ];
    }
}
