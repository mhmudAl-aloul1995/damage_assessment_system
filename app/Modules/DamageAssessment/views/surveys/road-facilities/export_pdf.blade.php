<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('multilingual.road_facilities_page.title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .heading { margin-bottom: 16px; }
        .heading h1 { margin: 0 0 6px; font-size: 20px; }
        .meta { color: #6b7280; font-size: 11px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }}; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 700; }
        .filters { margin-bottom: 16px; }
        .filters span { display: inline-block; margin-right: 12px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="heading">
        <h1>{{ __('multilingual.road_facilities_page.surveys_title') }}</h1>
        <div class="meta">{{ __('multilingual.road_facilities_page.generated_at') }} {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    <div class="filters">
        @if (! empty($filters['municipalitie']))<span><strong>{{ __('multilingual.road_facilities_page.municipality') }}:</strong> {{ $filters['municipalitie'] }}</span>@endif
        @if (! empty($filters['road_damage_level']))<span><strong>{{ __('multilingual.road_facilities_page.damage_level') }}:</strong> {{ $filters['road_damage_level'] }}</span>@endif
        @if (! empty($filters['assignedto']))<span><strong>{{ __('multilingual.road_facilities_page.researcher') }}:</strong> {{ $filters['assignedto'] }}</span>@endif
        @if (! empty($filters['from_date']))<span><strong>{{ __('multilingual.road_facilities_page.from') }}:</strong> {{ $filters['from_date'] }}</span>@endif
        @if (! empty($filters['to_date']))<span><strong>{{ __('multilingual.road_facilities_page.to') }}:</strong> {{ $filters['to_date'] }}</span>@endif
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('multilingual.road_facilities_page.object_id') }}</th>
                <th>{{ __('multilingual.road_facilities_page.road_name') }}</th>
                <th>{{ __('multilingual.road_facilities_page.municipality') }}</th>
                <th>{{ __('multilingual.road_facilities_page.neighborhood') }}</th>
                <th>{{ __('multilingual.road_facilities_page.damage_level') }}</th>
                <th>{{ __('multilingual.road_facilities_page.road_access') }}</th>
                <th>{{ __('multilingual.road_facilities_page.submission_date') }}</th>
                <th>{{ __('multilingual.road_facilities_page.linked_items') }}</th>
                <th>{{ __('multilingual.road_facilities_page.researcher') }}</th>
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
                    <td>{{ $survey->submissiondate?->format('Y-m-d H:i') }}</td>
                    <td>{{ $survey->items_count }}</td>
                    <td>{{ $survey->assignedto }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">{{ __('multilingual.road_facilities_page.no_surveys') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
