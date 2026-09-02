<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateDashboardCardRequest extends StoreDashboardCardRequest
{
    public function rules(): array
    {
        $dashboardCard = $this->route('dashboard_card') ?? $this->route('dashboardCard');

        return [
            ...parent::rules(),
            'key' => ['required', 'string', 'max:100', Rule::unique('dashboard_cards', 'key')->ignore($dashboardCard)],
        ];
    }
}
