<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>CSO Damage Assessment Export</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 10px;
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
            padding: 6px;
            text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }};
            vertical-align: top;
            word-break: break-word;
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
        <h1>CSO Damage Assessment Export</h1>
        <div class="meta">Generated at {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    <div class="filters">
        @foreach (['municipalitie' => 'Municipality', 'neighborhood' => 'Neighborhood', 'assignedto' => 'Researcher', 'building_damage_status' => 'Damage Status', 'operational_status' => 'Operational Status', 'from_date' => 'From', 'to_date' => 'To', 'q' => 'Search'] as $filter => $label)
            @if (! empty($filters[$filter]))
                <span><strong>{{ $label }}:</strong> {{ is_array($filters[$filter]) ? implode(', ', $filters[$filter]) : $filters[$filter] }}</span>
            @endif
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>Section Type</th>
                <th>Survey Object ID</th>
                <th>Survey Global ID</th>
                <th>Survey Organization Name</th>
                <th>Survey Building Name</th>
                <th>Child Object ID</th>
                <th>Child Global ID</th>
                <th>Repeat Index</th>
                <th>Field</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['Section Type'] }}</td>
                    <td>{{ $row['Survey Object ID'] }}</td>
                    <td>{{ $row['Survey Global ID'] }}</td>
                    <td>{{ $row['Survey Organization Name'] }}</td>
                    <td>{{ $row['Survey Building Name'] }}</td>
                    <td>{{ $row['Child Object ID'] }}</td>
                    <td>{{ $row['Child Global ID'] }}</td>
                    <td>{{ $row['Repeat Index'] }}</td>
                    <td>{{ $row['Field'] }}</td>
                    <td>{{ $row['Value'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No CSO surveys found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
