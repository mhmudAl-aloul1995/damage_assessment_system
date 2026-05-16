@extends('layouts.app')

@section('title', 'PHC PDF Report')
@section('pageName', 'PHC PDF Report')

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div>
                    <h2 class="mb-1">تقرير حصر الأضرار - PHC</h2>
                    <div class="text-muted fs-7">تقرير PDF عربي من 14 صفحة يعتمد على بيانات المباني والوحدات الحالية.</div>
                </div>
            </div>

            <div class="card-toolbar">
                <a href="{{ route('damage-assessment.reports.phc.export', request()->query()) }}" class="btn btn-primary" target="_blank">
                    Export PDF
                </a>
            </div>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('damage-assessment.reports.phc') }}" class="row g-4 mb-8">
                <div class="col-md-3">
                    <label class="form-label">Start date</label>
                    <input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label">End date</label>
                    <input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Governorate</label>
                    <select class="form-select" name="governorate">
                        <option value="">All</option>
                        @foreach ($governorates as $governorate)
                            <option value="{{ $governorate['english_name'] }}" @selected(request('governorate') === $governorate['english_name'])>
                                {{ $governorate['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Municipality</label>
                    <input type="text" class="form-control" name="municipalitie" value="{{ request('municipalitie') }}" placeholder="municipalitie">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-light-primary">Filter</button>
                    <a href="{{ route('damage-assessment.reports.phc') }}" class="btn btn-light">Reset</a>
                </div>
            </form>

            <div class="row g-4">
                <div class="col-md-3">
                    <div class="border rounded p-5 h-100">
                        <div class="fs-2 fw-bold text-primary">{{ number_format($totals['buildings']) }}</div>
                        <div class="text-muted">إجمالي المباني</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-5 h-100">
                        <div class="fs-2 fw-bold text-info">{{ number_format($totals['housing_units']) }}</div>
                        <div class="text-muted">إجمالي الوحدات</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-5 h-100">
                        <div class="fs-2 fw-bold text-warning">{{ number_format($totals['assessed_housing_units']) }}</div>
                        <div class="text-muted">الوحدات المقيمة</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-5 h-100">
                        <div class="fs-2 fw-bold text-success">{{ number_format($totals['affected_population']) }}</div>
                        <div class="text-muted">السكان المتأثرون تقديرياً</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
