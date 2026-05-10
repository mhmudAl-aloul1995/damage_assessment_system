<?php

namespace App\Exports;

use App\Models\TeamLeaderFieldEngineer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TeamLeaderFieldEngineersExport implements FromCollection, WithHeadings
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return TeamLeaderFieldEngineer::query()
            ->with(['teamLeader', 'fieldEngineer', 'creator'])
            ->when($this->filters['region'] ?? null, function ($q, $region) {
                $q->whereHas('fieldEngineer', fn ($qq) => $qq->where('region', $region));
            })
            ->when($this->filters['team_leader_id'] ?? null, fn ($q, $id) => $q->where('team_leader_id', $id))
            ->when($this->filters['field_engineer_id'] ?? null, fn ($q, $id) => $q->where('field_engineer_id', $id))
            ->latest('id')
            ->get()
            ->map(fn ($item) => [
                'team_leader' => $item->teamLeader?->name,
                'field_engineer' => $item->fieldEngineer?->name,
                'region' => $item->fieldEngineer?->region,
                'created_by' => $item->creator?->name,
                'created_at' => optional($item->created_at)->format('Y-m-d H:i'),
            ]);
    }

    public function headings(): array
    {
        return [
            'Team Leader',
            'Field Engineer',
            'Region',
            'Created By',
            'Created At',
        ];
    }
}
