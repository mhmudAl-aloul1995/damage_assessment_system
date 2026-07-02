@extends('layouts.app')

@section('title', 'جدول تسعير HEKS')
@section('pageName', 'إدارة جدول التسعير')

@section('content')
    <style>
        .heks-catalog-page .catalog-hero,
        .heks-catalog-page .catalog-panel,
        .heks-catalog-page .catalog-tools { border: 1px solid var(--bs-gray-200); border-radius: .75rem; background: var(--bs-body-bg); }
        .heks-catalog-page .catalog-hero { background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .08), rgba(var(--bs-success-rgb), .04)); }
        .heks-catalog-page .catalog-table { min-width: 1120px; table-layout: fixed; }
        .heks-catalog-page .catalog-table thead th { background: var(--bs-body-bg); position: sticky; top: 0; z-index: 1; }
        .heks-catalog-page .catalog-table-wrap { border: 1px solid var(--bs-gray-200); border-radius: .75rem; max-height: calc(100vh - 20rem); overflow: auto; }
        .heks-catalog-page .catalog-number { direction: ltr; text-align: right; font-variant-numeric: tabular-nums; unicode-bidi: plaintext; }
        .heks-catalog-page .catalog-row-inactive { opacity: .62; }
        .heks-catalog-page .catalog-row-hidden { display: none; }
        .heks-catalog-page .catalog-description { min-width: 0; }
    </style>

    <div class="heks-catalog-page">
        <div class="catalog-hero p-5 mb-6">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
                <div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge badge-light-primary">HEKS BOQ</span>
                        <span class="badge badge-light">كتالوج عام</span>
                    </div>
                    <h2 class="fw-bold mb-2">جدول التسعير الأساسي</h2>
                    <div class="text-muted">إدارة بنود الكتالوج التي تظهر في شاشة BOQ لكل مستفيد.</div>
                </div>
                <div class="d-flex flex-wrap gap-3">
                    <div class="catalog-panel px-4 py-3">
                        <div class="text-muted small">كل البنود</div>
                        <div class="fs-3 fw-bold">{{ number_format($totalItems) }}</div>
                    </div>
                    <div class="catalog-panel px-4 py-3">
                        <div class="text-muted small">البنود النشطة</div>
                        <div class="fs-3 fw-bold text-success">{{ number_format($activeCount) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-flush">
            <div class="card-body">
                @include('heks::partials.nav')

                <div class="catalog-panel p-4 mb-6">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h3 class="fs-5 fw-bold mb-1">إضافة بند تسعير</h3>
                            <div class="text-muted fs-7">البند المضاف هنا سيظهر في شاشة جدول الكميات الأساسي للمستفيدين.</div>
                        </div>
                        <span class="badge badge-light-primary">جديد</span>
                    </div>
                    <form method="POST" action="{{ route('heks.pricing-catalog.store') }}" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">القسم</label>
                            <input name="section" class="form-control" list="heksCatalogSections" placeholder="أعمال البلوك">
                        </div>
                        <div class="col-xl-1 col-md-3">
                            <label class="form-label">رقم</label>
                            <input name="item_code" class="form-control catalog-number" placeholder="3.1" dir="ltr">
                        </div>
                        <div class="col-xl-4 col-md-8">
                            <label class="form-label">وصف البند</label>
                            <input name="description" class="form-control" placeholder="وصف بند جدول التسعير" required>
                        </div>
                        <div class="col-xl-1 col-md-3">
                            <label class="form-label">الوحدة</label>
                            <input name="unit" class="form-control" list="heksCatalogUnits" placeholder="M2">
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">سعر الوحدة ILS</label>
                            <input name="unit_price_ils" type="number" min="0" step="0.01" class="form-control catalog-number" value="0" required dir="ltr">
                        </div>
                        <div class="col-xl-1 col-md-3">
                            <label class="form-label">الترتيب</label>
                            <input name="sort_order" type="number" min="0" class="form-control catalog-number" value="{{ $totalItems + 1 }}" dir="ltr">
                        </div>
                        <div class="col-xl-1 col-md-2">
                            <button class="btn btn-primary w-100">إضافة</button>
                        </div>
                        <div class="col-md-10">
                            <input name="notes" class="form-control" placeholder="ملاحظات اختيارية">
                        </div>
                        <div class="col-md-2">
                            <label class="form-check form-switch form-check-custom form-check-solid mt-3">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                <span class="form-check-label">نشط</span>
                            </label>
                        </div>
                    </form>
                </div>

                <datalist id="heksCatalogSections">
                    @foreach ($sections as $section)
                        <option value="{{ $section }}"></option>
                    @endforeach
                </datalist>
                <datalist id="heksCatalogUnits">
                    @foreach ($units as $unit)
                        <option value="{{ $unit }}"></option>
                    @endforeach
                </datalist>

                <div class="catalog-tools d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 p-4 mb-4">
                    <div class="d-flex flex-column flex-md-row gap-3 flex-grow-1">
                        <input type="search" class="form-control" id="heksCatalogSearch" placeholder="بحث بالقسم أو الرقم أو الوصف">
                        <select class="form-select" id="heksCatalogStatus">
                            <option value="">كل الحالات</option>
                            <option value="active">النشطة فقط</option>
                            <option value="inactive">غير النشطة فقط</option>
                        </select>
                    </div>
                    <div class="text-muted small">الظاهر الآن: <span class="fw-bold" id="heksCatalogVisibleCount">{{ number_format($totalItems) }}</span> بند</div>
                </div>

                <div class="table-responsive catalog-table-wrap">
                    <table class="table table-row-dashed align-middle catalog-table">
                        <thead>
                        <tr class="fw-bold text-muted">
                            <th style="width: 120px;">القسم</th>
                            <th style="width: 90px;">رقم</th>
                            <th>الوصف</th>
                            <th style="width: 90px;">الوحدة</th>
                            <th style="width: 130px;">سعر الوحدة</th>
                            <th style="width: 90px;">الترتيب</th>
                            <th style="width: 110px;">الحالة</th>
                            <th style="width: 180px;">ملاحظات</th>
                            <th style="width: 140px;"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($items as $item)
                            <tr class="{{ $item->is_active ? '' : 'catalog-row-inactive' }}" data-catalog-row data-status="{{ $item->is_active ? 'active' : 'inactive' }}" data-search-text="{{ Str::lower($item->section.' '.$item->item_code.' '.$item->description.' '.$item->unit) }}">
                                <form id="update-catalog-{{ $item->id }}" method="POST" action="{{ route('heks.pricing-catalog.update', $item) }}">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <form id="delete-catalog-{{ $item->id }}" method="POST" action="{{ route('heks.pricing-catalog.destroy', $item) }}" onsubmit="return confirm('هل تريد حذف بند التسعير؟')">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <td><input form="update-catalog-{{ $item->id }}" name="section" class="form-control form-control-sm" value="{{ $item->section }}"></td>
                                <td><input form="update-catalog-{{ $item->id }}" name="item_code" class="form-control form-control-sm catalog-number" value="{{ $item->item_code }}" dir="ltr"></td>
                                <td><textarea form="update-catalog-{{ $item->id }}" name="description" class="form-control form-control-sm catalog-description" rows="2" required>{{ $item->description }}</textarea></td>
                                <td><input form="update-catalog-{{ $item->id }}" name="unit" class="form-control form-control-sm" value="{{ $item->unit }}"></td>
                                <td><input form="update-catalog-{{ $item->id }}" name="unit_price_ils" type="number" min="0" step="0.01" class="form-control form-control-sm catalog-number" value="{{ $item->unit_price_ils }}" dir="ltr" required></td>
                                <td><input form="update-catalog-{{ $item->id }}" name="sort_order" type="number" min="0" class="form-control form-control-sm catalog-number" value="{{ $item->sort_order }}" dir="ltr"></td>
                                <td>
                                    <label class="form-check form-switch form-check-custom form-check-solid">
                                        <input form="update-catalog-{{ $item->id }}" class="form-check-input" type="checkbox" name="is_active" value="1" @checked($item->is_active)>
                                    </label>
                                </td>
                                <td><input form="update-catalog-{{ $item->id }}" name="notes" class="form-control form-control-sm" value="{{ $item->notes }}"></td>
                                <td class="text-nowrap">
                                    <button form="update-catalog-{{ $item->id }}" class="btn btn-sm btn-light-primary">حفظ</button>
                                    <button form="delete-catalog-{{ $item->id }}" class="btn btn-sm btn-light-danger">حذف</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-10">لا توجد بنود في جدول التسعير بعد.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (() => {
            const searchInput = document.getElementById('heksCatalogSearch');
            const statusSelect = document.getElementById('heksCatalogStatus');
            const visibleCount = document.getElementById('heksCatalogVisibleCount');
            const countFormatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });

            function normalize(value) {
                return String(value || '').trim().toLowerCase();
            }

            function applyFilters() {
                const search = normalize(searchInput?.value);
                const status = statusSelect?.value || '';
                let visible = 0;

                document.querySelectorAll('[data-catalog-row]').forEach((row) => {
                    const matchesSearch = !search || normalize(row.dataset.searchText).includes(search);
                    const matchesStatus = !status || row.dataset.status === status;
                    const shouldShow = matchesSearch && matchesStatus;

                    row.classList.toggle('catalog-row-hidden', !shouldShow);
                    if (shouldShow) visible += 1;
                });

                if (visibleCount) {
                    visibleCount.textContent = countFormatter.format(visible);
                }
            }

            searchInput?.addEventListener('input', applyFilters);
            statusSelect?.addEventListener('change', applyFilters);
        })();
    </script>
@endsection
