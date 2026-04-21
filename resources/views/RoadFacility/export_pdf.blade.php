<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Road Facilities Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .heading { margin-bottom: 16px; }
        .heading h1 { margin: 0 0 6px; font-size: 20px; }
        .meta { color: #6b7280; font-size: 11px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 700; }
        .filters { margin-bottom: 16px; }
        .filters span { display: inline-block; margin-right: 12px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="heading">
        <h1>Road Facilities Surveys</h1>
        <div class="meta">Generated at {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    <div class="filters">
        @if (! empty($filters['municipalitie']))<span><strong>Municipality:</strong> {{ $filters['municipalitie'] }}</span>@endif
        @if (! empty($filters['road_damage_level']))<span><strong>Damage Level:</strong> {{ $filters['road_damage_level'] }}</span>@endif
        @if (! empty($filters['assigned_to']))<span><strong>Researcher:</strong> {{ $filters['assigned_to'] }}</span>@endif
        @if (! empty($filters['from_date']))<span><strong>From:</strong> {{ $filters['from_date'] }}</span>@endif
        @if (! empty($filters['to_date']))<span><strong>To:</strong> {{ $filters['to_date'] }}</span>@endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Object ID</th>
                <th>Road Name</th>
                <th>Municipality</th>
                <th>Neighborhood</th>
                <th>Damage Level</th>
                <th>Road Access</th>
                <th>Submission Date</th>
                <th>Items</th>
                <th>Researcher</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($surveys as $survey)
                <tr>
                    <td>{{ $survey->objectid }}</td>
                    <td>{{ $survey->str_name }}</td>
                    <td>{{ $survey->municipalitie }}</td>
                    <td>{{ $survey->neighborhood }}</td>
                    <td>{{ $survey->road_damage_level }}</td>
                    <td>{{ $survey->road_access }}</td>
                    <td>{{ $survey->submission_date?->format('Y-m-d H:i') }}</td>
                    <td>{{ $survey->items_count }}</td>
                    <td>{{ $survey->assigned_to }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No surveys found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
