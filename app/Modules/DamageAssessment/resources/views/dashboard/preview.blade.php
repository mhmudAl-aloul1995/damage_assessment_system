@extends('layouts.app')

@section('title', 'الصفحة الرئيسية')
@section('pageName', 'الصفحة الرئيسية')

@section('content')


    <div id="damage-dashboard-preview" lang="ar" dir="rtl">
        <header class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-3 mb-2"><h2 class="text-gray-900 fw-bold mb-0">لوحة متابعة تقييم الأضرار</h2></div>
                <span class="text-muted fs-7">مؤشرات القطاعات وبنودها، حسب إعدادات الإدارة والفلاتر المختارة.</span>
            </div>
        </header>
        <section aria-label="بطاقات لوحة التحكم">
            <div class="d-flex justify-content-end mb-4">
                <button type="button" class="btn btn-sm btn-light-primary" data-dashboard-export-open>
                    <i class="ki-duotone ki-picture fs-4 me-1" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>
                    تصدير البطاقات
                </button>
            </div>
            <form method="GET" action="{{ route(request()->route()->getName()) }}" class="card mb-5" aria-label="فلاتر البطاقات الفعلية">
                <input type="hidden" name="period" value="all">
                <div class="card-body p-5">
                    <div class="row g-4 align-items-end">
                        <div class="col-sm-6 col-lg-3">
                            <label for="cards-governorate" class="form-label fs-7 fw-bold">المحافظة</label>
                            <select id="cards-governorate" name="governorate" class="form-select form-select-solid form-select-sm">
                                <option value="">كل المحافظات</option>
                                @foreach ($governorates as $governorate)
                                    <option value="{{ $governorate }}" @selected($dashboardFilters['selectedGovernorate'] === $governorate)>{{ $governorate }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label for="cards-neighborhood" class="form-label fs-7 fw-bold">الحي</label>
                            <select id="cards-neighborhood" name="neighborhood" class="form-select form-select-solid form-select-sm">
                                <option value="">كل الأحياء</option>
                                @foreach ($neighborhoods as $neighborhood)
                                    <option value="{{ $neighborhood }}" @selected($dashboardFilters['selectedNeighborhood'] === $neighborhood)>{{ $neighborhood }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-lg-2"><label for="cards-from-date" class="form-label fs-7 fw-bold">من تاريخ</label><input id="cards-from-date" name="from_date" type="date" value="{{ $dashboardFilters['startDate'] }}" class="form-control form-control-solid form-control-sm"></div>
                        <div class="col-6 col-lg-2"><label for="cards-to-date" class="form-label fs-7 fw-bold">إلى تاريخ</label><input id="cards-to-date" name="to_date" type="date" value="{{ $dashboardFilters['endDate'] }}" class="form-control form-control-solid form-control-sm"></div>
                        <div class="col-lg-2 d-flex gap-2"><button type="submit" class="btn btn-sm btn-primary flex-grow-1">تطبيق</button><a href="{{ route(request()->route()->getName()) }}" class="btn btn-sm btn-light">مسح</a></div>
                    </div>
                </div>
            </form>
            @include('damage-assessment::dashboard.partials.summary-cards', ['compactDashboardCards' => true])
        </section>

        <section class="card mb-6" aria-labelledby="preview-maps-heading">
            <div class="card-header align-items-center gap-3">
                <h2 id="preview-maps-heading" class="card-title fw-bold">خرائط GIS للقطاعات</h2>
            </div>
            <div class="card-body p-5">
                <p class="text-muted fs-7">اختر القطاع لاستعراض بياناته الجغرافية. فلاتر المحافظة والحي والتاريخ أعلاه تُطبّق على الخرائط أيضًا.</p>
                <div class="nav nav-pills gap-2 mb-5" role="group" aria-label="قطاعات خرائط GIS">
                    @foreach ($previewGis['sectors'] as $key => $sector)
                        <button type="button" class="btn btn-sm {{ $loop->first ? 'btn-primary' : 'btn-light' }}" data-gis-sector="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $sector['title'] }}</button>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <h3 id="preview-gis-title" class="fs-5 mb-0">{{ reset($previewGis['sectors'])['title'] }}</h3>
                    <div class="d-flex gap-2">
                        <button type="button" id="preview-gis-fit" class="btn btn-sm btn-light-primary" disabled>عرض كامل الطبقة</button>
                        <a id="preview-gis-records" href="{{ reset($previewGis['sectors'])['listUrl'] }}" class="btn btn-sm btn-light-primary">فتح سجلات القطاع</a>
                    </div>
                </div>
                <div id="preview-gis-message" class="bg-light rounded p-4 mb-4 text-gray-700 fs-7" role="status" aria-live="polite">{{ $previewGis['error'] ?? 'جارٍ تحميل طبقة GIS…' }}</div>
                <button type="button" id="preview-gis-retry" class="btn btn-sm btn-light-primary mb-4" hidden>إعادة المحاولة</button>
                <div id="dashboard_preview_gis_map" class="h-500px rounded overflow-hidden bg-light" aria-label="خريطة GIS الفعلية"></div>
                <noscript><div class="alert alert-warning mt-4">فعّل JavaScript لعرض خرائط GIS.</div></noscript>
            </div>
        </section>
    </div>
    <div class="modal fade" id="dashboard_cards_export_modal" tabindex="-1" aria-labelledby="dashboard_cards_export_title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="dashboard_cards_export_title">تصدير البطاقات كصورة</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="bg-light rounded p-4 text-center">
                        <img src="" alt="معاينة صورة البطاقات" class="img-fluid rounded shadow-sm d-none" data-dashboard-export-preview>
                        <div class="text-muted fs-7" data-dashboard-export-status>اضغط تصدير البطاقات لإنشاء المعاينة.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-light-primary" data-dashboard-export-refresh>تحديث المعاينة</button>
                    <a href="#" class="btn btn-primary disabled" download="damage-assessment-dashboard-cards.png" data-dashboard-export-download aria-disabled="true">
                        تنزيل PNG
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script type="application/json" id="preview-gis-data">@json($previewGis)</script>
@endsection

@section('script')
    @php($previewScriptVersion = filemtime(base_path('assets/js/custom/DamageAssessment/dashboard-preview.js')))
    <script type="module" src="{{ asset('assets/js/custom/DamageAssessment/dashboard-preview.js') }}?v={{ $previewScriptVersion }}"></script>
@endsection
