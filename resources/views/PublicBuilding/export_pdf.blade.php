<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Public Buildings Export</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 12px;
        }

        .heading {
            margin-bottom: 16px;
        }

        .heading h1 {
            margin: 0 0 6px;
            font-size: 20px;
        }

        .meta {
            color: #6b7280;
            font-size: 11px;
            margin-bottom: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
        }

        .filters {
            margin-bottom: 16px;
        }

        .filters span {
            display: inline-block;
            margin-right: 12px;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="heading">
        <h1>Public Building Surveys</h1>
        <div class="meta">Generated at {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    <div class="filters">
        @if (! empty($filters['municipalitie']))
            <span><strong>Municipality:</strong> {{ $filters['municipalitie'] }}</span>
        @endif
        @if (! empty($filters['building_damage_status']))
            <span><strong>Damage Status:</strong> {{ $filters['building_damage_status'] }}</span>
        @endif
        @if (! empty($filters['assigned_to']))
            <span><strong>Researcher:</strong> {{ $filters['assigned_to'] }}</span>
        @endif
        @if (! empty($filters['from_date']))
            <span><strong>From:</strong> {{ $filters['from_date'] }}</span>
        @endif
        @if (! empty($filters['to_date']))
            <span><strong>To:</strong> {{ $filters['to_date'] }}</span>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Object ID</th>
                <th>Building Name</th>
                <th>Municipality</th>
                <th>Neighborhood</th>
                <th>Damage Status</th>
                <th>Date Of Damage</th>
                <th>Units</th>
                <th>Researcher</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($surveys as $survey)
                <tr>
                    <td>{{ $survey->objectid }}</td>
                    <td>{{ $survey->building_name }}</td>
                    <td>{{ $survey->municipalitie }}</td>
                    <td>{{ $survey->neighborhood }}</td>
                    <td>{{ $survey->building_damage_status }}</td>
                    <td>{{ $survey->date_of_damage?->format('Y-m-d') }}</td>
                    <td>{{ $survey->units_count }}</td>
                    <td>{{ $survey->assigned_to }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No surveys found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
