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

    $formatRecord = function (?array $record): string {
        if (! $record) {
            return '-';
        }

        return json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
    };

    $signatureStatuses = [
        'approved' => 'موافق',
        'rejected' => 'مرفوض',
        'pending' => 'بانتظار التوقيع',
    ];
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

    <div class="card card-flush border border-gray-200 mb-5">
        <div class="card-header">
            <div class="card-title"><h3 class="fw-bold m-0">أعضاء اللجنة والتوقيعات</h3></div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 mb-0">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>العضو</th>
                            <th>الصفة</th>
                            <th>الحالة</th>
                            <th>تاريخ التوقيع</th>
                            <th>بواسطة</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($committeeMembers as $committeeMember)
                            @php
                                $signatureStatus = data_get($committeeMember, 'status');
                                $statusClass = $signatureStatus === 'approved'
                                    ? 'success'
                                    : ($signatureStatus === 'rejected' ? 'danger' : 'warning');
                            @endphp
                            <tr>
                                <td>{{ data_get($committeeMember, 'name') ?: '-' }}</td>
                                <td>{{ data_get($committeeMember, 'title') ?: '-' }}</td>
                                <td><span class="badge badge-light-{{ $statusClass }}">{{ $signatureStatuses[$signatureStatus] ?? '-' }}</span></td>
                                <td>{{ data_get($committeeMember, 'signed_at') ?: '-' }}</td>
                                <td>{{ data_get($committeeMember, 'signed_by') ?: '-' }}</td>
                                <td>{{ data_get($committeeMember, 'notes') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-6">لا توجد بيانات أعضاء لجنة لهذا السجل.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('damage-assessment::committee.archive.partials.comparison-table', [
        'title' => 'بيانات المبنى',
        'rows' => $buildingRows,
        'formatValue' => $formatValue,
        'previousRecord' => $formatRecord($archiveObject->building_snapshot),
        'currentRecord' => $formatRecord($currentBuilding?->attributesToArray()),
        'missingCurrent' => $currentBuilding === null,
    ])

    @if ($archiveObject->housing_unit_objectid || $archiveObject->housing_unit_snapshot)
        @include('damage-assessment::committee.archive.partials.comparison-table', [
            'title' => 'بيانات الوحدة السكنية',
            'rows' => $housingRows,
            'formatValue' => $formatValue,
            'previousRecord' => $formatRecord($archiveObject->housing_unit_snapshot),
            'currentRecord' => $formatRecord($currentHousingUnit?->attributesToArray()),
            'missingCurrent' => $currentHousingUnit === null,
        ])
    @endif

    @include('damage-assessment::committee.archive.partials.comparison-table', [
        'title' => 'بيانات قرار اللجنة',
        'rows' => $decisionRows,
        'formatValue' => $formatValue,
        'previousRecord' => $formatRecord($archiveObject->committee_decision_snapshot),
        'currentRecord' => $formatRecord($archiveObject->committeeDecision?->attributesToArray()),
        'missingCurrent' => $archiveObject->committeeDecision === null,
    ])
@endsection
