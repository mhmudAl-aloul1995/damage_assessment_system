<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير تدقيق الطرق</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; direction: rtl; }
        .heading { margin-bottom: 16px; }
        .heading h1 { margin: 0 0 6px; font-size: 20px; }
        .meta { color: #6b7280; font-size: 11px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: center; vertical-align: middle; }
        th { background: #f3f4f6; font-weight: 700; }
        tfoot td { background: #eef2ff; font-weight: 700; }
        .filters { margin-bottom: 16px; }
        .filters span { display: inline-block; margin-left: 12px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="heading">
        <h1>تقرير تدقيق الطرق</h1>
        <div class="meta">تاريخ التصدير: {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    <div class="filters">
        @foreach ($filters as $label => $value)
            @if (filled($value))
                <span><strong>{{ $label }}:</strong> {{ $value }}</span>
            @endif
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>المحافظة</th>
                <th>الحي</th>
                <th>ما تم حصره</th>
                <th>ما تم تدقيقه</th>
                <th>أطوال الطرق (متر)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['governorate'] }}</td>
                    <td>{{ $row['neighborhood'] }}</td>
                    <td>{{ $row['surveyed_count'] }}</td>
                    <td>{{ $row['audited_count'] }}</td>
                    <td>{{ number_format((float) $row['road_length_meters'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">لا توجد بيانات ضمن الفلاتر الحالية.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows !== [])
            <tfoot>
                <tr>
                    <td colspan="2">الإجمالي</td>
                    <td>{{ collect($rows)->sum('surveyed_count') }}</td>
                    <td>{{ collect($rows)->sum('audited_count') }}</td>
                    <td>{{ number_format((float) collect($rows)->sum('road_length_meters'), 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
