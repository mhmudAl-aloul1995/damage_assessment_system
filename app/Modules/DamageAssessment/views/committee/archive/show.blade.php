@extends('layouts.app')

@php
    $recordType = filled($archiveObject->housing_unit_objectid) ? 'وحدة سكنية' : 'مبنى';
    $recordId = $archiveObject->housing_unit_objectid ?: $archiveObject->building_objectid;
    $sourceLabel = $archiveObject->source_type === 'temporary_committee_excel_archive' ? 'أرشفة Excel الاستثنائية' : 'قرار لجنة';
    $comparisonSections = collect([
        ['id' => 'building', 'title' => 'بيانات المبنى', 'rows' => $buildingRows, 'previousRecord' => $archiveObject->building_snapshot, 'currentRecord' => $currentBuilding?->attributesToArray(), 'missingCurrent' => $currentBuilding === null],
        ['id' => 'housing-unit', 'title' => 'بيانات الوحدة السكنية', 'rows' => $housingRows, 'previousRecord' => $archiveObject->housing_unit_snapshot, 'currentRecord' => $currentHousingUnit?->attributesToArray(), 'missingCurrent' => $currentHousingUnit === null],
        ['id' => 'committee-decision', 'title' => 'بيانات قرار اللجنة', 'rows' => $decisionRows, 'previousRecord' => $archiveObject->committee_decision_snapshot, 'currentRecord' => $archiveObject->committeeDecision?->attributesToArray(), 'missingCurrent' => $archiveObject->committeeDecision === null],
    ])->reject(fn (array $section): bool => $section['id'] === 'housing-unit' && ! $archiveObject->housing_unit_objectid && ! $archiveObject->housing_unit_snapshot);
    $changedFieldsCount = $comparisonSections->sum(fn (array $section): int => collect($section['rows'])->where('changed', true)->count());
    $approvedSignaturesCount = collect($committeeMembers)->where('status', 'approved')->count();
    $pendingSignaturesCount = collect($committeeMembers)->where('status', 'pending')->count();
    $formatValue = function ($value): string {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
        }

        return filled($value) ? (string) $value : '-';
    };
    $formatRecord = fn (?array $record): string => $record ? (json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-') : '-';
    $signatureStatuses = ['approved' => 'موافق', 'rejected' => 'مرفوض', 'pending' => 'بانتظار التوقيع'];
@endphp

@section('title', 'مقارنة سجل اللجنة الفنية')
@section('pageName', 'مقارنة سجل اللجنة الفنية')

@section('content')
    <div class="card card-flush border border-gray-200 mb-5"><div class="card-body py-6"><div class="d-flex flex-wrap justify-content-between align-items-start gap-4"><div><div class="d-flex align-items-center gap-3 mb-2"><span class="badge badge-light-primary">{{ $recordType }}</span><span class="text-muted fs-7">أرشفة بتاريخ {{ optional($archiveObject->archived_at)->format('Y-m-d H:i') ?? '-' }}</span></div><h2 class="fw-bold mb-1">مراجعة التغييرات</h2><div class="text-muted">رقم السجل <span class="fw-semibold text-gray-800">{{ $recordId }}</span> · {{ $sourceLabel }}</div></div><a href="{{ route('committee-archive.index') }}" class="btn btn-light btn-sm">رجوع إلى الأرشيف</a></div></div></div>

    @if (! $archiveObject->building_snapshot)
        <div class="alert alert-warning mb-5">لا يحتوي هذا السجل على نسخة قديمة كاملة، لذلك قد تظهر بعض المقارنات بقيم فارغة.</div>
    @endif

    <div class="row g-5 mb-5">
        @foreach ([['الحقول المتغيرة', $changedFieldsCount, 'تغيير', 'warning'], ['توقيعات مكتملة', $approvedSignaturesCount, 'من '.count($committeeMembers), 'success'], ['بانتظار التوقيع', $pendingSignaturesCount, 'عضو', $pendingSignaturesCount ? 'warning' : 'gray-700']] as [$label, $value, $suffix, $color])
            <div class="col-sm-6 col-xl-3"><div class="card card-flush border border-gray-200 h-100"><div class="card-body py-5"><div class="text-muted fs-7 mb-1">{{ $label }}</div><div class="d-flex align-items-baseline gap-2"><span class="fs-2hx fw-bold text-{{ $color }}">{{ $value }}</span><span class="text-muted">{{ $suffix }}</span></div></div></div></div>
        @endforeach
        <div class="col-sm-6 col-xl-3"><div class="card card-flush border border-gray-200 h-100"><div class="card-body py-5"><div class="text-muted fs-7 mb-1">مصدر الأرشفة</div><div class="fw-bold fs-5">{{ $sourceLabel }}</div></div></div></div>
    </div>

    <div class="card card-flush border border-gray-200 mb-5"><div class="card-header"><div class="card-title"><h3 class="fw-bold m-0">أعضاء اللجنة والتوقيعات</h3></div></div><div class="card-body"><div class="row g-4">
        @forelse ($committeeMembers as $committeeMember)
            @php $signatureStatus = data_get($committeeMember, 'status'); $statusClass = $signatureStatus === 'approved' ? 'success' : ($signatureStatus === 'rejected' ? 'danger' : 'warning'); @endphp
            <div class="col-md-6 col-xl-4"><div class="border border-gray-200 rounded p-4 h-100"><div class="d-flex justify-content-between gap-3 mb-3"><div><div class="fw-bold">{{ data_get($committeeMember, 'name') ?: '-' }}</div><div class="text-muted fs-7">{{ data_get($committeeMember, 'title') ?: 'عضو لجنة' }}</div></div><span class="badge badge-light-{{ $statusClass }} align-self-start">{{ $signatureStatuses[$signatureStatus] ?? '-' }}</span></div><div class="text-muted fs-8">{{ data_get($committeeMember, 'signed_at') ? 'وُقّع في '.data_get($committeeMember, 'signed_at') : 'لم يتم التوقيع بعد' }}</div>@if (filled(data_get($committeeMember, 'notes')))<div class="text-gray-700 fs-7 mt-3">{{ data_get($committeeMember, 'notes') }}</div>@endif</div></div>
        @empty
            <div class="col-12 text-center text-muted py-6">لا توجد بيانات أعضاء لجنة لهذا السجل.</div>
        @endforelse
    </div></div></div>

    @foreach ($comparisonSections as $section)
        @include('damage-assessment::committee.archive.partials.comparison-table', ['id' => $section['id'], 'title' => $section['title'], 'rows' => $section['rows'], 'formatValue' => $formatValue, 'previousRecord' => $formatRecord($section['previousRecord']), 'currentRecord' => $formatRecord($section['currentRecord']), 'missingCurrent' => $section['missingCurrent']])
    @endforeach
@endsection
