@extends('layouts.app')

@section('title', 'أرشيف قرارات اللجنة الفنية')
@section('pageName', 'أرشيف قرارات اللجنة الفنية')

@section('content')
    <div class="card card-flush shadow-sm mb-5">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold m-0">سجلات اللجنة الفنية المؤرشفة</h3>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('committee-archive.index') }}" class="row g-4 align-items-end mb-6">
                <div class="col-md-3">
                    <label class="form-label">ObjectID</label>
                    <input type="text" name="objectid" value="{{ $filters['objectid'] ?? '' }}" class="form-control form-control-solid">
                </div>
                <div class="col-md-3">
                    <label class="form-label">نوع السجل</label>
                    <select name="record_type" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        <option value="building" @selected(($filters['record_type'] ?? '') === 'building')>مباني</option>
                        <option value="housing-unit" @selected(($filters['record_type'] ?? '') === 'housing-unit')>وحدات سكنية</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">مصدر الأرشفة</label>
                    <select name="source_type" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        <option value="committee_decision" @selected(($filters['source_type'] ?? '') === 'committee_decision')>قرار لجنة</option>
                        <option value="temporary_committee_excel_archive" @selected(($filters['source_type'] ?? '') === 'temporary_committee_excel_archive')>أرشفة Excel الاستثنائية</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">النسخة القديمة</label>
                    <select name="snapshot" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        <option value="available" @selected(($filters['snapshot'] ?? '') === 'available')>موجودة</option>
                        <option value="missing" @selected(($filters['snapshot'] ?? '') === 'missing')>غير موجودة</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">بحث</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>النوع</th>
                            <th>ObjectID</th>
                            <th>المصدر</th>
                            <th>تاريخ الأرشفة</th>
                            <th>القرار</th>
                            <th>النسخة القديمة</th>
                            <th class="text-end">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($archives as $archive)
                            @php
                                $isHousing = filled($archive->housing_unit_objectid);
                                $sourceLabel = $archive->source_type === 'temporary_committee_excel_archive'
                                    ? 'Excel استثنائي'
                                    : 'قرار لجنة';
                            @endphp
                            <tr>
                                <td>{{ $isHousing ? 'وحدة سكنية' : 'مبنى' }}</td>
                                <td>
                                    <div class="fw-bold">{{ $isHousing ? $archive->housing_unit_objectid : $archive->building_objectid }}</div>
                                    @if ($isHousing)
                                        <div class="text-muted fs-7">مبنى: {{ $archive->building_objectid }}</div>
                                    @endif
                                </td>
                                <td><span class="badge badge-light-info">{{ $sourceLabel }}</span></td>
                                <td>{{ optional($archive->archived_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>{{ $archive->committeeDecision?->decision_type ?? '-' }}</td>
                                <td>
                                    @if ($archive->building_snapshot)
                                        <span class="badge badge-light-success">موجودة</span>
                                    @else
                                        <span class="badge badge-light-warning">غير موجودة</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('committee-archive.show', $archive) }}" class="btn btn-light-primary btn-sm">عرض المقارنة</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-10">لا توجد سجلات مطابقة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $archives->links() }}
            </div>
        </div>
    </div>
@endsection
