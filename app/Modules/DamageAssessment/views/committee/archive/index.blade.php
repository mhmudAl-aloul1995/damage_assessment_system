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
                <div class="col-md-2">
                    <label class="form-label">ObjectID</label>
                    <input type="text" name="objectid" value="{{ $filters['objectid'] ?? '' }}" class="form-control form-control-solid">
                </div>

                <div class="col-md-2">
                    <label class="form-label">نوع السجل</label>
                    <select name="record_type" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        <option value="building" @selected(($filters['record_type'] ?? '') === 'building')>مباني</option>
                        <option value="housing-unit" @selected(($filters['record_type'] ?? '') === 'housing-unit')>وحدات سكنية</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">البلدية</label>
                    <select name="municipality" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality }}" @selected(($filters['municipality'] ?? '') === $municipality)>{{ $municipality }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
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

                <div class="col-md-2">
                    <label class="form-label">حالة الضرر القديمة</label>
                    <select name="old_damage_status" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        @foreach (['committee_review', 'committee_review2', 'fully_damaged', 'fully_damaged2', 'partially_damaged', 'partially_damaged2'] as $status)
                            <option value="{{ $status }}" @selected(($filters['old_damage_status'] ?? '') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">حالة الضرر الحالية</label>
                    <select name="current_damage_status" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        @foreach (['committee_review', 'committee_review2', 'fully_damaged', 'fully_damaged2', 'partially_damaged', 'partially_damaged2'] as $status)
                            <option value="{{ $status }}" @selected(($filters['current_damage_status'] ?? '') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">field_status</label>
                    <select name="field_status" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        @foreach (['COMPLETED', 'Not_Completed'] as $status)
                            <option value="{{ $status }}" @selected(($filters['field_status'] ?? '') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="archived_from" value="{{ $filters['archived_from'] ?? '' }}" class="form-control form-control-solid">
                </div>

                <div class="col-md-2">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="archived_to" value="{{ $filters['archived_to'] ?? '' }}" class="form-control form-control-solid">
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">بحث</button>
                    <a href="{{ route('committee-archive.index') }}" class="btn btn-light">تصفير</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>النوع</th>
                            <th>ObjectID</th>
                            <th>البلدية</th>
                            <th>مصدر الأرشفة</th>
                            <th>تاريخ الأرشفة</th>
                            <th>القرار</th>
                            <th>أعضاء اللجنة</th>
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
                                $municipality = $isHousing
                                    ? data_get($archive->housing_unit_snapshot, 'municipalitie')
                                    : data_get($archive->building_snapshot, 'municipalitie');
                                $municipality = $municipality ?: $archive->building?->municipalitie;
                                $committeeMembers = collect(data_get($archive->committee_decision_snapshot, 'committee_members', []));

                                if ($committeeMembers->isEmpty()) {
                                    $committeeMembers = $archive->committeeDecision?->signatures
                                        ->sortBy('sort_order')
                                        ->map(fn ($signature) => ['name' => $signature->committeeMember?->name])
                                        ->values() ?? collect();
                                }
                            @endphp
                            <tr>
                                <td>{{ $isHousing ? 'وحدة سكنية' : 'مبنى' }}</td>
                                <td>
                                    <div class="fw-bold">{{ $isHousing ? $archive->housing_unit_objectid : $archive->building_objectid }}</div>
                                    @if ($isHousing)
                                        <div class="text-muted fs-7">مبنى: {{ $archive->building_objectid }}</div>
                                    @endif
                                </td>
                                <td>{{ $municipality ?: '-' }}</td>
                                <td><span class="badge badge-light-info">{{ $sourceLabel }}</span></td>
                                <td>{{ optional($archive->archived_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>{{ $archive->committeeDecision?->decision_type ?? '-' }}</td>
                                <td>
                                    @forelse ($committeeMembers as $committeeMember)
                                        <span class="badge badge-light-primary mb-1">{{ data_get($committeeMember, 'name') ?: '-' }}</span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                </td>
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
                                <td colspan="9" class="text-center text-muted py-10">لا توجد سجلات مطابقة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $archives->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
