@extends('layouts.app')

@section('title', 'تسعير HEKS BOQ')
@section('pageName', 'تسعير جدول الكميات')

@section('content')
    <style>
        .heks-pricing-page .pricing-number { direction: ltr; text-align: right; font-variant-numeric: tabular-nums; unicode-bidi: plaintext; }
        .heks-pricing-page .pricing-page-header { background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .08), rgba(var(--bs-success-rgb), .05)); border: 1px solid var(--bs-gray-200); border-radius: .75rem; }
        .heks-pricing-page .pricing-summary-card, .heks-pricing-page .pricing-tools, .heks-pricing-page .pricing-save-bar, .heks-pricing-page .pricing-manage-card { background: var(--bs-body-bg); border: 1px solid var(--bs-gray-200); border-radius: .75rem; }
        .heks-pricing-page .pricing-summary-card { height: 100%; padding: 1.2rem; }
        .heks-pricing-page .pricing-tools, .heks-pricing-page .pricing-manage-card { padding: 1rem; }
        .heks-pricing-page .pricing-table { min-width: 1220px; table-layout: fixed; }
        .heks-pricing-page .pricing-table thead th { background: var(--bs-body-bg); position: sticky; top: 0; z-index: 1; }
        .heks-pricing-page .pricing-table tbody tr.is-priced-row { background: rgba(var(--bs-success-rgb), .035); }
        .heks-pricing-page .pricing-table tbody tr.is-hidden-by-filter { display: none; }
        .heks-pricing-page .pricing-table tbody tr.pricing-section-row { background: #f8fbff; }
        .heks-pricing-page .pricing-table tbody tr.pricing-section-row.is-hidden-by-filter { display: none; }
        .heks-pricing-page .pricing-table-wrap { border: 1px solid var(--bs-gray-200); border-radius: .75rem; max-height: calc(100vh - 18rem); overflow: auto; }
        .heks-pricing-page .pricing-save-bar { bottom: 1rem; box-shadow: 0 .65rem 1.75rem rgba(15, 23, 42, .08); position: sticky; z-index: 2; }
        .heks-pricing-page .pricing-col-code { width: 88px; }
        .heks-pricing-page .pricing-col-section { width: 150px; }
        .heks-pricing-page .pricing-col-item { width: 34%; }
        .heks-pricing-page .pricing-col-unit { width: 80px; }
        .heks-pricing-page .pricing-col-money { width: 130px; }
        .heks-pricing-page .pricing-col-quantity { width: 105px; }
        .heks-pricing-page .pricing-col-notes { width: 180px; }
        .heks-pricing-page .pricing-col-actions { width: 110px; }
        .heks-pricing-page .pricing-item-description { line-height: 1.55; overflow-wrap: anywhere; }
        .heks-pricing-page .pricing-section-strip { border-inline-start: 4px solid var(--bs-primary); padding: .75rem 1rem; }
    </style>

    @php
        $savedItemsCount = $pricingRows->filter(fn ($row) => (float) $row['quantity'] > 0)->count();
        $catalogItemsCount = $pricingRows->count();
    @endphp

    <div class="heks-pricing-page">
        <div class="pricing-page-header p-5 mb-6">
            <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-5">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge badge-light-primary">تسعير BOQ</span>
                        <span class="badge badge-light">{{ $beneficiary->code }}</span>
                    </div>
                    <h2 class="fw-bold mb-2">{{ $beneficiary->name ?? '-' }}</h2>
                    <div class="text-muted fs-6">{{ $beneficiary->identity_number ?: '-' }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('heks.beneficiaries.edit', $beneficiary) }}" class="btn btn-light">بيانات المستفيد</a>
                    <a href="{{ route('heks.beneficiaries') }}" class="btn btn-light-primary">رجوع</a>
                    <button type="submit" form="heksPricingForm" class="btn btn-primary">حفظ التعديلات</button>
                </div>
            </div>
        </div>

        <div class="card card-flush">
            <div class="card-body">
                @include('heks::partials.nav')

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <div class="row g-5 mb-8">
                    <div class="col-md-4">
                        <div class="pricing-summary-card">
                            <div class="text-muted fs-7 mb-1">الإجمالي بالشيكل</div>
                            <div class="fs-2 fw-bold text-success" id="pricingGrandTotalIls">{{ number_format($boqTotal, 2) }} ILS</div>
                            <div class="text-muted fs-8 mt-2">مجموع البنود ذات الكمية لهذا المستفيد</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="pricing-summary-card">
                            <div class="text-muted fs-7 mb-1">عدد البنود المحفوظة</div>
                            <div class="fs-2 fw-bold"><span id="pricingActiveCount">{{ $savedItemsCount }}</span> / {{ $catalogItemsCount }}</div>
                            <div class="text-muted fs-8 mt-2">البنود التي كميتها أكبر من صفر</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="pricing-summary-card">
                            <div class="text-muted fs-7 mb-1">قيمة المنحة</div>
                            <div class="fs-2 fw-bold text-primary">{{ $beneficiary->grant_amount ? number_format((float) $beneficiary->grant_amount, 2) : '-' }} ILS</div>
                            <div class="text-muted fs-8 mt-2">للمقارنة مع إجمالي جدول الكميات</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-6">
                    <div class="col-xl-8">
                        <div class="pricing-manage-card h-100">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                                <div>
                                    <h3 class="fs-5 fw-bold mb-1">إضافة بند إلى BOQ الأساسي</h3>
                                    <div class="text-muted fs-7">أضف بندا خارج الكتالوج أو بندا ميدانيا خاصا بهذا المستفيد.</div>
                                </div>
                                <span class="badge badge-light-primary">يدوي</span>
                            </div>
                            <form method="POST" action="{{ route('heks.beneficiaries.boq-items.store', $beneficiary) }}" class="row g-3 align-items-end">
                                @csrf
                                <input type="hidden" name="source" value="manual">
                                <div class="col-md-3">
                                    <label class="form-label">القسم</label>
                                    <input name="section" class="form-control" list="heksPricingSections" placeholder="القسم">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">رقم البند</label>
                                    <input name="item_code" class="form-control" placeholder="3.1">
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">وصف البند</label>
                                    <input name="description" class="form-control" placeholder="وصف البند" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">الوحدة</label>
                                    <input name="unit" class="form-control" list="heksPricingUnits" placeholder="M2">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">الكمية</label>
                                    <input name="quantity" type="number" min="0" step="0.001" class="form-control pricing-number" value="0" required dir="ltr">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">سعر الوحدة</label>
                                    <input name="unit_price_ils" type="number" min="0" step="0.01" class="form-control pricing-number" value="0" required dir="ltr">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ملاحظات</label>
                                    <input name="notes" class="form-control" placeholder="ملاحظة اختيارية">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-primary w-100">إضافة</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="pricing-manage-card h-100">
                            <h3 class="fs-5 fw-bold mb-1">استيراد BOQ أساسي</h3>
                            <div class="text-muted fs-7 mb-4">يراجع النظام كود الطلب أو اسم المستفيد قبل ترحيل البنود.</div>
                            <form method="POST" action="{{ route('heks.beneficiaries.boq-items.import', $beneficiary) }}" enctype="multipart/form-data" class="d-flex flex-column gap-3">
                                @csrf
                                <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                                <button class="btn btn-light-primary">استيراد ملف Excel</button>
                            </form>
                        </div>
                    </div>
                </div>

                <datalist id="heksPricingSections">
                    @foreach ($pricingSections as $section)
                        <option value="{{ $section['section'] }}"></option>
                    @endforeach
                </datalist>
                <datalist id="heksPricingUnits">
                    @foreach ($pricingRows->pluck('unit')->filter()->unique()->sort()->values() as $unit)
                        <option value="{{ $unit }}"></option>
                    @endforeach
                </datalist>

                <form method="POST" action="{{ route('heks.beneficiaries.pricing.update', $beneficiary) }}" id="heksPricingForm">
                    @csrf
                    @method('PUT')
                </form>

                    <div class="pricing-tools d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 mb-4">
                        <div class="d-flex flex-column flex-md-row gap-3 flex-grow-1">
                            <input type="search" class="form-control" id="pricingSearchInput" placeholder="بحث بالكود أو اسم البند أو القسم">
                            <select class="form-select" id="pricingSectionFilter" aria-label="فلترة القسم">
                                <option value="">كل الأقسام</option>
                                @foreach ($pricingSections as $section)
                                    <option value="{{ md5($section['section']) }}">{{ $section['section'] }}</option>
                                @endforeach
                            </select>
                            <label class="form-check form-switch form-check-custom form-check-solid align-items-center">
                                <input class="form-check-input" type="checkbox" value="1" id="pricingActiveOnlyToggle">
                                <span class="form-check-label text-muted">عرض البنود المسعّرة فقط</span>
                            </label>
                        </div>
                        <div class="text-muted fs-7">الظاهر الآن: <span class="fw-bold text-gray-800" id="pricingVisibleCount">{{ $catalogItemsCount }}</span> بند</div>
                    </div>

                    <div class="table-responsive pricing-table-wrap">
                        <table class="table table-row-dashed align-middle pricing-table" id="pricingTable">
                            <colgroup>
                                <col class="pricing-col-code">
                                <col class="pricing-col-section">
                                <col class="pricing-col-item">
                                <col class="pricing-col-unit">
                                <col class="pricing-col-money">
                                <col class="pricing-col-quantity">
                                <col class="pricing-col-money">
                                <col class="pricing-col-notes">
                                <col class="pricing-col-actions">
                            </colgroup>
                            <thead>
                            <tr class="fw-bold text-muted">
                                <th>الكود</th>
                                <th>القسم</th>
                                <th>البند</th>
                                <th>الوحدة</th>
                                <th>سعر الوحدة ILS</th>
                                <th>الكمية</th>
                                <th>الإجمالي ILS</th>
                                <th>ملاحظات</th>
                                <th>الإجراء</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($pricingRows as $index => $row)
                                @php
                                    $sectionName = $row['section'] ?: 'بدون قسم';
                                    $sectionKey = md5($sectionName);
                                    $previousSection = $pricingRows[$index - 1]['section'] ?? null;
                                @endphp
                                @if ($index === 0 || $previousSection !== $row['section'])
                                    @php
                                        $sectionSummary = $pricingSections->firstWhere('section', $sectionName);
                                    @endphp
                                    <tr class="pricing-section-row" data-section-header="{{ $sectionKey }}">
                                        <td colspan="9">
                                            <div class="pricing-section-strip d-flex flex-column flex-md-row justify-content-between gap-2">
                                                <div>
                                                    <div class="fw-bold text-gray-800">{{ $sectionName }}</div>
                                                    <div class="text-muted small">أدخل الكميات المطلوبة فقط، واترك باقي البنود فارغة أو صفر.</div>
                                                </div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <span class="badge badge-light-primary">{{ number_format($sectionSummary['items_count'] ?? 0) }} بند</span>
                                                    <span class="badge badge-light-success">{{ number_format($sectionSummary['priced_count'] ?? 0) }} مسعر</span>
                                                    <span class="badge badge-light">{{ number_format((float) ($sectionSummary['total'] ?? 0), 2) }} ILS</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                                <tr data-pricing-row data-section-key="{{ $sectionKey }}" data-search-text="{{ Str::lower($row['item_code'].' '.$row['section'].' '.$row['description'].' '.$row['unit']) }}">
                                    <td>
                                        <input form="heksPricingForm" type="hidden" name="items[{{ $index }}][source]" value="{{ $row['source'] }}">
                                        <input form="heksPricingForm" type="text" name="items[{{ $index }}][item_code]" value="{{ $row['item_code'] }}" class="form-control form-control-sm form-control-solid">
                                    </td>
                                    <td><input form="heksPricingForm" type="text" name="items[{{ $index }}][section]" value="{{ $row['section'] }}" class="form-control form-control-sm form-control-solid"></td>
                                    <td>
                                        <input form="heksPricingForm" type="hidden" name="items[{{ $index }}][description]" value="{{ $row['description'] }}">
                                        <div class="fw-semibold pricing-item-description">{{ $row['description'] }}</div>
                                    </td>
                                    <td><input form="heksPricingForm" type="text" name="items[{{ $index }}][unit]" value="{{ $row['unit'] }}" class="form-control form-control-sm form-control-solid"></td>
                                    <td><input form="heksPricingForm" type="text" inputmode="decimal" name="items[{{ $index }}][unit_price_ils]" value="{{ $row['unit_price_ils'] }}" class="form-control form-control-sm pricing-number" data-unit-price dir="ltr"></td>
                                    <td><input form="heksPricingForm" type="text" inputmode="decimal" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" class="form-control form-control-sm pricing-number" data-quantity dir="ltr"></td>
                                    <td class="fw-bold pricing-number text-success" data-row-total>{{ number_format((float) $row['total_price_ils'], 2) }}</td>
                                    <td><input form="heksPricingForm" type="text" name="items[{{ $index }}][notes]" value="{{ $row['notes'] }}" class="form-control form-control-sm" placeholder="ملاحظة"></td>
                                    <td>
                                        @if ($row['id'])
                                            <form id="delete-pricing-row-{{ $row['id'] }}" method="POST" action="{{ route('heks.boq-items.destroy', $row['id']) }}" onsubmit="return confirm('هل تريد حذف هذا البند من BOQ الأساسي؟')">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                            <button form="delete-pricing-row-{{ $row['id'] }}" class="btn btn-sm btn-light-danger w-100">حذف</button>
                                        @else
                                            <span class="badge badge-light">كتالوج</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted py-10">لا توجد بنود BOQ بعد.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="pricing-save-bar d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 mt-5">
                        <div>
                            <div class="fw-bold">جاهز للحفظ</div>
                            <div class="text-muted fs-7">سيتم حفظ البنود التي كميتها أكبر من صفر فقط.</div>
                        </div>
                        <button type="submit" form="heksPricingForm" class="btn btn-primary">حفظ التعديلات</button>
                    </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (() => {
            const formatter = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const countFormatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
            const table = document.getElementById('pricingTable');
            const grandTotalIls = document.getElementById('pricingGrandTotalIls');
            const activeCount = document.getElementById('pricingActiveCount');
            const visibleCount = document.getElementById('pricingVisibleCount');
            const searchInput = document.getElementById('pricingSearchInput');
            const sectionFilter = document.getElementById('pricingSectionFilter');
            const activeOnlyToggle = document.getElementById('pricingActiveOnlyToggle');

            function normalize(value) { return String(value || '').trim().toLowerCase(); }

            function applyFilters() {
                const searchTerm = normalize(searchInput?.value);
                const selectedSection = sectionFilter?.value || '';
                const activeOnly = Boolean(activeOnlyToggle?.checked);
                const visibleSections = new Set();
                let visible = 0;

                document.querySelectorAll('[data-pricing-row]').forEach((row) => {
                    const quantity = Number(row.querySelector('[data-quantity]')?.value || 0);
                    const matchesSearch = !searchTerm || normalize(row.dataset.searchText).includes(searchTerm);
                    const matchesSection = !selectedSection || row.dataset.sectionKey === selectedSection;
                    const matchesActive = !activeOnly || quantity > 0;
                    const isVisible = matchesSearch && matchesSection && matchesActive;

                    row.classList.toggle('is-hidden-by-filter', !isVisible);
                    if (isVisible) {
                        visible += 1;
                        visibleSections.add(row.dataset.sectionKey);
                    }
                });

                document.querySelectorAll('[data-section-header]').forEach((row) => {
                    row.classList.toggle('is-hidden-by-filter', !visibleSections.has(row.dataset.sectionHeader));
                });

                if (visibleCount) visibleCount.textContent = countFormatter.format(visible);
            }

            function recalculate() {
                let totalIls = 0;
                let active = 0;

                document.querySelectorAll('[data-pricing-row]').forEach((row) => {
                    const unitPrice = Number(row.querySelector('[data-unit-price]')?.value || 0);
                    const quantity = Number(row.querySelector('[data-quantity]')?.value || 0);
                    const rowTotal = unitPrice * quantity;

                    totalIls += rowTotal;
                    if (quantity > 0) active += 1;

                    row.classList.toggle('is-priced-row', quantity > 0);
                    row.querySelector('[data-row-total]').textContent = formatter.format(rowTotal);
                });

                grandTotalIls.textContent = `${formatter.format(totalIls)} ILS`;
                if (activeCount) activeCount.textContent = countFormatter.format(active);
                applyFilters();
            }

            table?.addEventListener('input', (event) => {
                if (event.target.matches('[data-unit-price], [data-quantity]')) recalculate();
            });
            searchInput?.addEventListener('input', applyFilters);
            sectionFilter?.addEventListener('change', applyFilters);
            activeOnlyToggle?.addEventListener('change', applyFilters);
            recalculate();
        })();
    </script>
@endsection
