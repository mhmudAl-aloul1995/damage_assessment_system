<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('ui.housing_page.title') }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 11px;
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
    </style>
</head>
<body>
    <div class="heading">
        <h1>{{ __('ui.housing_page.title') }}</h1>
        <div class="meta">{{ now()->format('Y-m-d H:i') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($housingColumns as $column)
                    <th>{{ $assessmentHints[$column]->hint ?? $assessmentHints[$column]->label ?? str($column)->replace('_', ' ')->title() }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($housing as $housingUnit)
                <tr>
                    @foreach ($housingColumns as $column)
                        <td>{{ $housingUnit->{$column} }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($housingColumns), 1) }}">لا توجد بيانات</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
