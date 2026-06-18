@extends('layouts.app')

@php
    $recordType = filled($archiveObject->housing_unit_objectid) ? 'وحدة سكنية' : 'مبنى';
    $sourceLabel = $archiveObject->source_type === 'temporary_committee_excel_archive'
        ? 'أرشفة Excel الاستثنائية'
        : 'قرار لجنة';

    $formatValue = function ($value): string {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    };
@endphp

@section('title', 'مقارنة سجل اللجنة الفنية')
@section('pageName', 'مقارنة سجل اللجنة الفنية')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold mb-1">مقارنة القديم والحالي</h3>
            <div class="text-muted">يعرض السجل المؤرشف مقابل السجل الحالي في قاعدة البيانات.</div>
        </div>
        <a href="{{ route('committee-archive.index') }}" class="btn btn-light btn-sm">رجوع</a>
    </div>

    @if (! $archiveObject->building_snapshot)
        <div class="alert alert-warning mb-5">
            هذا السجل لا يحتوي نسخة قديمة كاملة. غالباً تم إنشاؤه قبل إضافة أعمدة snapshot.
        </div>
    @endif

    <div class="row g-5 mb-5">
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-1">نوع السجل</div>
                    <div class="fw-bold">{{ $recordType }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-1">ObjectID</div>
                    <div class="fw-bold">{{ $archiveObject->housing_unit_objectid ?: $archiveObject->building_objectid }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-1">مصدر الأرشفة</div>
                    <div class="fw-bold">{{ $sourceLabel }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body">
                    <div class="text-muted fs-7 mb-1">تاريخ الأرشفة</div>
                    <div class="fw-bold">{{ optional($archiveObject->archived_at)->format('Y-m-d H:i') ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    @include('damage-assessment::committee.archive.partials.comparison-table', [
        'title' => 'بيانات المبنى',
        'rows' => $buildingRows,
        'formatValue' => $formatValue,
        'missingCurrent' => $currentBuilding === null,
    ])

    @if ($archiveObject->housing_unit_objectid || $archiveObject->housing_unit_snapshot)
        @include('damage-assessment::committee.archive.partials.comparison-table', [
            'title' => 'بيانات الوحدة السكنية',
            'rows' => $housingRows,
            'formatValue' => $formatValue,
            'missingCurrent' => $currentHousingUnit === null,
        ])
    @endif

    @include('damage-assessment::committee.archive.partials.comparison-table', [
        'title' => 'بيانات قرار اللجنة',
        'rows' => $decisionRows,
        'formatValue' => $formatValue,
        'missingCurrent' => $archiveObject->committeeDecision === null,
    ])
@endsection
