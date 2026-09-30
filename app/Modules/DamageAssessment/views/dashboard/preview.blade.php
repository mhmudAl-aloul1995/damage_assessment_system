@extends('layouts.app')

@section('title', 'معاينة الصفحة الرئيسية')
@section('pageName', 'معاينة الصفحة الرئيسية')

@section('content')
    @php
        $sectors = [
            'buildings' => ['title' => 'المباني', 'route' => 'building.index'],
            'housing' => ['title' => 'الوحدات السكانية', 'route' => 'housing.index'],
            'cso' => ['title' => 'منظمات المجتمع المدني', 'route' => 'cso-surveys.index'],
            'public' => ['title' => 'المباني العامة', 'route' => 'public-buildings.index'],
            'roads' => ['title' => 'الطرق', 'route' => 'road-facilities.index'],
        ];
        $locations = [
            ['governorate' => 'غزة', 'municipality' => 'غزة', 'longitude' => 34.46, 'latitude' => 31.51],
            ['governorate' => 'غزة', 'municipality' => 'الزهراء', 'longitude' => 34.40, 'latitude' => 31.47],
            ['governorate' => 'شمال غزة', 'municipality' => 'بيت لاهيا', 'longitude' => 34.50, 'latitude' => 31.55],
            ['governorate' => 'دير البلح', 'municipality' => 'دير البلح', 'longitude' => 34.35, 'latitude' => 31.41],
            ['governorate' => 'خانيونس', 'municipality' => 'خانيونس', 'longitude' => 34.30, 'latitude' => 31.34],
            ['governorate' => 'رفح', 'municipality' => 'رفح', 'longitude' => 34.25, 'latitude' => 31.28],
        ];
        $statuses = [
            'completed' => ['label' => 'مكتمل', 'tone' => 'success', 'color' => '#50cd89'],
            'review' => ['label' => 'بانتظار المراجعة', 'tone' => 'warning', 'color' => '#ffc700'],
            'blocked' => ['label' => 'متعذّر التقييم', 'tone' => 'danger', 'color' => '#f1416c'],
        ];
        $records = [];
        foreach (array_keys($sectors) as $sectorIndex => $sectorKey) {
            foreach ($locations as $locationIndex => $location) {
                $id = count($records) + 1;
                $records[] = [
                    'id' => $id,
                    'code' => 'DEMO-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT),
                    'sector' => $sectorKey,
                    'sectorLabel' => $sectors[$sectorKey]['title'],
                    'governorate' => $location['governorate'],
                    'municipality' => $location['municipality'],
                    'date' => '2026-09-'.(20 + $locationIndex),
                    'status' => array_keys($statuses)[($sectorIndex + $locationIndex) % 3],
                    'longitude' => $location['longitude'] + $sectorIndex * 0.003,
                    'latitude' => $location['latitude'] + $sectorIndex * 0.002,
                ];
            }
        }
        $previewData = ['records' => $records, 'statuses' => $statuses, 'sectors' => $sectors];
        $summaryCards = [
            ['key' => 'total', 'title' => 'إجمالي النتائج', 'tone' => 'primary', 'icon' => 'ki-element-11'],
            ['key' => 'completed', 'title' => 'تقييمات مكتملة', 'tone' => 'success', 'icon' => 'ki-check-circle'],
            ['key' => 'review', 'title' => 'بانتظار المراجعة', 'tone' => 'warning', 'icon' => 'ki-time'],
            ['key' => 'blocked', 'title' => 'متعذّر التقييم', 'tone' => 'danger', 'icon' => 'ki-shield-cross'],
        ];
    @endphp

    <div id="damage-dashboard-preview" lang="ar" dir="rtl">
        <header class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-6">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-3 mb-2"><h2 class="text-gray-900 fw-bold mb-0">لوحة متابعة تقييم الأضرار</h2><span class="badge badge-light-primary">معاينة Metronic</span></div>
                <span class="text-muted fs-7">مؤشرات القطاعات وبنودها، حسب إعدادات الإدارة والفلاتر المختارة.</span>
            </div>
            <a href="{{ route('damageAssessment.index') }}" class="btn btn-sm btn-light-primary">العودة للصفحة الحالية <i class="ki-outline ki-arrow-left fs-5 ms-2" aria-hidden="true"></i></a>
        </header>
        <section aria-labelledby="preview-live-heading">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                <h2 id="preview-live-heading" class="fs-4 fw-bold mb-0">بطاقات لوحة التحكم</h2>
                <span class="badge badge-light-success">بيانات فعلية</span>
            </div>
            <p class="text-muted fs-7 mb-5">البطاقات والبنود المفعّلة حسب إعدادات الإدارة، وتُحسب أرقامها وفق الفلاتر التالية.</p>
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

        <details class="mb-6" id="preview-demo-section">
            <summary class="card p-5 text-primary fw-bold mb-5">استعراض مقترح الجدول والخريطة — بيانات تجريبية</summary>
        <div class="notice d-flex bg-light-warning rounded border border-dashed border-warning p-4 mb-6" role="note">
            <i class="ki-outline ki-information-5 text-warning fs-2 me-3" aria-hidden="true"></i>
            <div class="fs-7 text-gray-700"><strong>معاينة تصميم · بيانات توضيحية.</strong> السجلات والأرقام ومواقع الخريطة في هذا القسم أمثلة افتراضية. فلاتره مستقلة عن البطاقات الفعلية أعلاه.</div>
        </div>
        <noscript><div class="alert alert-warning">فعّل JavaScript لاستخدام الفلاتر والتبويبات والخريطة.</div></noscript>

        <form id="preview-filters" class="card mb-6" aria-label="فلاتر المعاينة">
            <div class="card-body p-5">
                <div class="row g-4 align-items-end">
                    <div class="col-sm-6 col-lg-3"><label for="preview-governorate" class="form-label fs-7 fw-bold">المحافظة</label><select id="preview-governorate" name="governorate" class="form-select form-select-solid form-select-sm"><option value="">كل المحافظات</option>@foreach (array_unique(array_column($locations, 'governorate')) as $governorate)<option value="{{ $governorate }}">{{ $governorate }}</option>@endforeach</select></div>
                    <div class="col-sm-6 col-lg-3"><label for="preview-municipality" class="form-label fs-7 fw-bold">البلدية</label><select id="preview-municipality" name="municipality" class="form-select form-select-solid form-select-sm"><option value="">كل البلديات</option>@foreach ($locations as $location)<option value="{{ $location['municipality'] }}">{{ $location['municipality'] }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label for="preview-date-from" class="form-label fs-7 fw-bold">من تاريخ</label><input id="preview-date-from" name="from" type="date" class="form-control form-control-solid form-control-sm"></div>
                    <div class="col-6 col-lg-2"><label for="preview-date-to" class="form-label fs-7 fw-bold">إلى تاريخ</label><input id="preview-date-to" name="to" type="date" class="form-control form-control-solid form-control-sm" aria-describedby="preview-date-error"></div>
                    <div class="col-lg-2"><button type="reset" class="btn btn-sm btn-light w-100">إعادة ضبط</button></div>
                </div>
                <p id="preview-date-error" class="text-danger fs-7 mt-4 mb-0" role="alert" hidden>تاريخ البداية يجب أن يسبق تاريخ النهاية. صحّح الفترة لتحديث النتائج.</p>
                <div class="text-muted fs-8 mt-4">فترة البيانات التجريبية: 20–25 سبتمبر 2026 · الفلاتر والبحث تؤثر على المؤشرات والجدول والخريطة في هذا القسم فقط.</div>
            </div>
        </form>

        <section class="row row-cols-2 row-cols-lg-4 g-5 mb-6" aria-label="ملخص النتائج المفلترة">
            @foreach ($summaryCards as $summaryCard)
                <div class="col"><div class="card card-flush h-100"><div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-4"><span class="symbol symbol-35px"><span class="symbol-label bg-light-{{ $summaryCard['tone'] }}"><i class="ki-outline {{ $summaryCard['icon'] }} text-{{ $summaryCard['tone'] }} fs-2" aria-hidden="true"></i></span></span><span class="badge badge-light fs-9">توضيحي</span></div>
                    <div class="fs-2hx fw-bold text-gray-900 mb-2" data-preview-stat="{{ $summaryCard['key'] }}">{{ $summaryCard['key'] === 'total' ? count($records) : count(array_filter($records, fn (array $record): bool => $record['status'] === $summaryCard['key'])) }}</div>
                    <h3 class="fs-7 text-gray-600 mb-0">{{ $summaryCard['title'] }}</h3>
                </div></div></div>
            @endforeach
        </section>

        <section class="card mb-6" aria-label="سجلات التقييم التجريبية">
            <div class="card-header border-0 px-6">
                <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-7 fw-bold gap-5" role="tablist" aria-label="قطاعات تقييم الأضرار">
                    <li class="nav-item" role="presentation"><button id="preview-tab-overview" class="nav-link text-active-primary active py-5" data-bs-toggle="tab" data-bs-target="#preview-pane-overview" data-preview-sector="all" type="button" role="tab" aria-controls="preview-pane-overview" aria-selected="true">نظرة عامة</button></li>
                    @foreach ($sectors as $key => $sector)
                        <li class="nav-item" role="presentation"><button id="preview-tab-{{ $key }}" class="nav-link text-active-primary py-5" data-bs-toggle="tab" data-bs-target="#preview-pane-{{ $key }}" data-preview-sector="{{ $key }}" type="button" role="tab" aria-controls="preview-pane-{{ $key }}" aria-selected="false" tabindex="-1">{{ $sector['title'] }}</button></li>
                    @endforeach
                </ul>
            </div>
            <div class="card-body border-top border-gray-200 p-6">
                <div class="tab-content mb-5">
                    <div class="tab-pane fade show active" id="preview-pane-overview" role="tabpanel" aria-labelledby="preview-tab-overview" tabindex="0"><h3 class="fs-5 fw-bold mb-2">كل القطاعات في عرض واحد</h3><span class="text-muted fs-8">أربع مؤشرات فقط، والتفاصيل حسب القطاع والفلاتر المختارة.</span></div>
                    @foreach ($sectors as $key => $sector)
                        <div class="tab-pane fade" id="preview-pane-{{ $key }}" role="tabpanel" aria-labelledby="preview-tab-{{ $key }}" tabindex="0"><div class="d-flex flex-wrap justify-content-between gap-3 align-items-center"><div><h3 class="fs-5 fw-bold mb-2">سجلات {{ $sector['title'] }}</h3><span class="text-muted fs-8">نتائج تجريبية مفلترة؛ رابط النظام يفتح قائمة القطاع الفعلية دون فلاتر المعاينة.</span></div><a href="{{ route($sector['route']) }}" class="btn btn-sm btn-light-primary">فتح القطاع في النظام ←</a></div></div>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mb-5">
                    <div class="flex-grow-1 mw-350px"><label for="preview-search" class="visually-hidden">بحث في السجلات</label><input id="preview-search" type="search" class="form-control form-control-solid form-control-sm" placeholder="بحث بالرمز أو البلدية أو حالة التقييم…"></div>
                    <div class="d-flex gap-3 align-items-center"><span id="preview-result-count" class="badge badge-light-primary" role="status" aria-live="polite">30 نتيجة</span><div class="btn-group" role="group" aria-label="طريقة عرض النتائج"><button type="button" class="btn btn-sm btn-light-primary active" data-preview-view="table" aria-pressed="true" aria-controls="preview-table-panel"><i class="ki-outline ki-row-horizontal fs-4" aria-hidden="true"></i>الجدول</button><button type="button" class="btn btn-sm btn-light" data-preview-view="map" aria-pressed="false" aria-controls="preview-map-panel"><i class="ki-outline ki-map fs-4" aria-hidden="true"></i>الخريطة</button></div></div>
                </div>
                <div id="preview-empty" class="text-center bg-light rounded p-8 mb-4" role="status" hidden><i class="ki-outline ki-search-list fs-3x text-gray-400" aria-hidden="true"></i><h4 class="fs-5 mt-4">لا توجد نتائج مطابقة</h4><p class="text-muted fs-7 mb-0">جرّب تغيير الفلاتر أو مسح البحث أو إعادة الضبط.</p></div>
                <div id="preview-table-panel">
                    <div class="table-responsive"><table class="table table-row-dashed align-middle gs-0 gy-4 mb-0"><caption class="text-muted fs-8">سجلات افتراضية للمعاينة؛ ترتيبها حسب الرمز.</caption><thead><tr class="text-gray-500 fw-bold fs-8"><th scope="col">الرمز التجريبي</th><th scope="col">القطاع</th><th scope="col">المحافظة</th><th scope="col">البلدية</th><th scope="col">تاريخ التقييم</th><th scope="col">الحالة</th></tr></thead><tbody id="preview-records">
                        @foreach (array_slice($records, 0, 8) as $record)
                            <tr><th scope="row" class="fw-bold text-gray-800 fs-7"><bdi>{{ $record['code'] }}</bdi></th><td class="text-gray-600 fs-7">{{ $record['sectorLabel'] }}</td><td class="text-gray-600 fs-7">{{ $record['governorate'] }}</td><td class="text-gray-600 fs-7">{{ $record['municipality'] }}</td><td class="text-gray-600 fs-7"><bdi>{{ $record['date'] }}</bdi></td><td><span class="badge badge-light-{{ $statuses[$record['status']]['tone'] }}">{{ $statuses[$record['status']]['label'] }}</span></td></tr>
                        @endforeach
                    </tbody></table></div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-5"><span id="preview-page-info" class="text-muted fs-8">1–8 من 30</span><nav class="d-flex gap-2" aria-label="صفحات نتائج المعاينة"><button type="button" id="preview-page-previous" class="btn btn-sm btn-light" disabled>السابق</button><button type="button" id="preview-page-next" class="btn btn-sm btn-light-primary">التالي</button></nav></div>
                </div>
                <div id="preview-map-panel" hidden>
                    <div class="d-flex flex-wrap gap-4 mb-4 fs-8 text-gray-600">@foreach ($statuses as $status)<span><span class="bullet bullet-dot bg-{{ $status['tone'] }} me-2" aria-hidden="true"></span>{{ $status['label'] }}</span>@endforeach<span class="text-muted">نقطة لكل سجل في النتائج المفلترة · مواقع افتراضية</span></div>
                    <div id="preview-map-message" class="bg-light rounded p-4 mb-4 text-gray-700 fs-7" role="status" aria-live="polite">اختر عرض الخريطة لتحميلها.</div>
                    <button type="button" id="preview-map-retry" class="btn btn-sm btn-light-primary mb-4" hidden>إعادة محاولة تحميل الخريطة</button>
                    <div id="dashboard_preview_gis_map" class="h-400px rounded overflow-hidden bg-light" aria-label="خريطة مواقع السجلات التجريبية"></div>
                </div>
            </div>
        </section>
        </details>
    </div>
    <script type="application/json" id="preview-dashboard-data">@json($previewData)</script>
@endsection

@section('script')
    <script type="module" src="{{ asset('assets/js/custom/DamageAssessment/dashboard-preview.js') }}"></script>
@endsection
