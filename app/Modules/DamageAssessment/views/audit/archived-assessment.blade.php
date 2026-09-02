@extends('layouts.app')

@section('title', __('ui.building_deletions.archived_assessment_title'))
@section('pageName', __('ui.building_deletions.archived_assessment_title'))

@php
    $formatValue = function (mixed $value): string {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? __('ui.building_deletions.yes') : __('ui.building_deletions.no');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
        }

        return (string) $value;
    };

    $buildingSummaryFields = [
        'objectid',
        'building_name',
        'floor_nos',
        'ground_floor_area__m2',
        'floor_area_m2',
        'building_roof_type',
        'concrete_area',
        'scorite_area',
        'assignedto',
        'governorate',
        'municipalitie',
        'neighborhood',
        'building_damage_status',
        'field_status',
    ];

    $housingUnitFields = [
        'objectid',
        'globalid',
        'housing_unit_number',
        'unit_owner',
        'q_9_3_1_first_name',
        'q_9_3_4_last_name',
        'damage_status',
        'housing_damage_status',
        'occupancy_status',
    ];

    $buildingDetails = collect($buildingRecord)
        ->reject(fn (mixed $value, string $key): bool => in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at'], true))
        ->sortKeys();
@endphp

@section('content')
    <div class="alert alert-warning border border-warning d-flex align-items-start gap-4 mb-6">
        <i class="ki-duotone ki-information-5 fs-2x text-warning"></i>
        <div>
            <div class="fw-bold fs-5">{{ __('ui.building_deletions.archived_notice_title') }}</div>
            <div>{{ __('ui.building_deletions.archived_notice_text') }}</div>
        </div>
    </div>

    <div class="card card-flush mb-6">
        <div class="card-body py-6">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-4">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge badge-light-warning">{{ __('ui.building_deletions.archived_badge') }}</span>
                        <span class="badge badge-light-primary">{{ __('ui.building_deletions.archived_source_'.$source) }}</span>
                    </div>
                    <h2 class="fw-bold mb-2">{{ $formatValue($buildingRecord['building_name'] ?? null) }}</h2>
                    <div class="text-muted">
                        {{ __('ui.building_deletions.object_id') }}:
                        <span class="fw-semibold text-gray-800">{{ $formatValue($buildingRecord['objectid'] ?? null) }}</span>
                        <span class="mx-2">|</span>
                        {{ __('ui.building_deletions.global_id') }}:
                        <span class="fw-semibold text-gray-800">{{ $formatValue($buildingGlobalId) }}</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if ($deletionRequest)
                        <a href="{{ route('building-deletions.show', $deletionRequest) }}" class="btn btn-sm btn-light-primary">
                            {{ __('ui.building_deletions.open_deletion_request') }}
                        </a>
                    @endif
                    @if ($snapshot && $deletionRequest && (auth()->user()?->can('viewRawSnapshot', $deletionRequest) ?? false))
                        <a href="{{ route('building-deletions.raw-snapshot', $snapshot->request_id) }}" class="btn btn-sm btn-light">
                            {{ __('ui.building_deletions.view_raw_json') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-6 mb-6">
        @foreach ($buildingSummaryFields as $field)
            @php
                $labelKey = 'ui.building_deletions.archived_fields.'.$field;
                $label = __($labelKey);
            @endphp
            <div class="col-md-3 col-sm-6">
                <div class="border rounded p-4 h-100 bg-light">
                    <div class="text-muted fs-8 mb-1">{{ $label === $labelKey ? str($field)->replace('_', ' ')->headline() : $label }}</div>
                    <div class="fw-bold text-gray-900 text-break">{{ $formatValue($buildingRecord[$field] ?? null) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-6">
        <div class="col-xl-7">
            <div class="card card-flush h-100">
                <div class="card-header">
                    <h3 class="card-title">{{ __('ui.building_deletions.archived_building_data') }}</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.building_deletions.field') }}</th>
                                    <th>{{ __('ui.building_deletions.value') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($buildingDetails as $field => $value)
                                    @php
                                        $labelKey = 'ui.building_deletions.archived_fields.'.$field;
                                        $label = __($labelKey);
                                    @endphp
                                    <tr>
                                        <td class="text-muted">{{ $label === $labelKey ? str($field)->replace('_', ' ')->headline() : $label }}</td>
                                        <td class="fw-semibold text-break">{{ $formatValue($value) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card card-flush h-100">
                <div class="card-header">
                    <h3 class="card-title">{{ __('ui.building_deletions.archived_housing_units') }}</h3>
                </div>
                <div class="card-body">
                    @if ($housingUnitRecords === [])
                        <div class="text-muted">{{ __('ui.building_deletions.no_archived_housing_units') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle">
                                <thead>
                                    <tr>
                                        @foreach ($housingUnitFields as $field)
                                            @php
                                                $labelKey = 'ui.building_deletions.archived_fields.'.$field;
                                                $label = __($labelKey);
                                            @endphp
                                            <th>{{ $label === $labelKey ? str($field)->replace('_', ' ')->headline() : $label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($housingUnitRecords as $unit)
                                        <tr>
                                            @foreach ($housingUnitFields as $field)
                                                <td class="text-break">{{ $formatValue($unit[$field] ?? null) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
