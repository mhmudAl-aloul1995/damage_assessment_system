@extends('layouts.app')

@section('title', 'تسعير HEKS BOQ')
@section('pageName', 'تسعير جدول الكميات')

@section('content')
    <style>
        .heks-pricing-page .pricing-number { direction: ltr; text-align: right; font-variant-numeric: tabular-nums; unicode-bidi: plaintext; }
        .heks-pricing-page .pricing-page-header { background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .08), rgba(var(--bs-success-rgb), .05)); border: 1px solid var(--bs-gray-200); border-radius: .75rem; }
        .heks-pricing-page .pricing-summary-card, .heks-pricing-page .pricing-tools, .heks-pricing-page .pricing-save-bar { background: var(--bs-body-bg); border: 1px solid var(--bs-gray-200); border-radius: .75rem; }
        .heks-pricing-page .pricing-summary-card { height: 100%; padding: 1.2rem; }
        .heks-pricing-page .pricing-tools { padding: 1rem; }
        .heks-pricing-page .pricing-table { min-width: 1050px; table-layout: fixed; }
        .heks-pricing-page .pricing-table thead th { background: var(--bs-body-bg); position: sticky; top: 0; z-index: 1; }
        .heks-pricing-page .pricing-table tbody tr.is-priced-row { background: rgba(var(--bs-success-rgb), .035); }
        .heks-pricing-page .pricing-table tbody tr.is-hidden-by-filter { display: none; }
        .heks-pricing-page .pricing-table-wrap { border: 1px solid var(--bs-gray-200); border-radius: .75rem; max-height: calc(100vh - 18rem); overflow: auto; }
        .heks-pricing-page .pricing-save-bar { bottom: 1rem; box-shadow: 0 .65rem 1.75rem rgba(15, 23, 42, .08); position: sticky; z-index: 2; }
        .heks-pricing-page .pricing-col-code { width: 88px; }
        .heks-pricing-page .pricing-col-section { width: 150px; }
        .heks-pricing-page .pricing-col-item { width: 38%; }
        .heks-pricing-page .pricing-col-unit { width: 80px; }
        .heks-pricing-page .pricing-col-money { width: 130px; }
        .heks-pricing-page .pricing-col-quantity { width: 105px; }
        .heks-pricing-page .pricing-item-description { line-height: 1.55; overflow-wrap: anywhere; }
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
                    <button type="submit" form="heksPricingForm" class="btn btn-primary">حفظ التسعير</button>
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

                <form method="POST" action="{{ route('heks.beneficiaries.pricing.update', $beneficiary) }}" id="heksPricingForm">
                    @csrf
                    @method('PUT')

                    <div class="pricing-tools d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 mb-4">
                        <div class="d-flex flex-column flex-md-row gap-3 flex-grow-1">
                            <input type="search" class="form-control" id="pricingSearchInput" placeholder="بحث بالكود أو اسم البند أو القسم">
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
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($pricingRows as $index => $row)
                                <tr data-pricing-row data-search-text="{{ Str::lower($row['item_code'].' '.$row['section'].' '.$row['description'].' '.$row['unit']) }}">
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][source]" value="{{ $row['source'] }}">
                                        <input type="text" name="items[{{ $index }}][item_code]" value="{{ $row['item_code'] }}" class="form-control form-control-sm form-control-solid">
                                    </td>
                                    <td><input type="text" name="items[{{ $index }}][section]" value="{{ $row['section'] }}" class="form-control form-control-sm form-control-solid"></td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][description]" value="{{ $row['description'] }}">
                                        <div class="fw-semibold pricing-item-description">{{ $row['description'] }}</div>
                                    </td>
                                    <td><input type="text" name="items[{{ $index }}][unit]" value="{{ $row['unit'] }}" class="form-control form-control-sm form-control-solid"></td>
                                    <td><input type="text" inputmode="decimal" name="items[{{ $index }}][unit_price_ils]" value="{{ $row['unit_price_ils'] }}" class="form-control form-control-sm pricing-number" data-unit-price dir="ltr"></td>
                                    <td><input type="text" inputmode="decimal" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" class="form-control form-control-sm pricing-number" data-quantity dir="ltr"></td>
                                    <td class="fw-bold pricing-number text-success" data-row-total>{{ number_format((float) $row['total_price_ils'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-10">لا توجد بنود BOQ بعد.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="pricing-save-bar d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 mt-5">
                        <div>
                            <div class="fw-bold">جاهز للحفظ</div>
                            <div class="text-muted fs-7">سيتم حفظ البنود التي كميتها أكبر من صفر فقط.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">حفظ التسعير</button>
                    </div>
                </form>
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
            const activeOnlyToggle = document.getElementById('pricingActiveOnlyToggle');

            function normalize(value) { return String(value || '').trim().toLowerCase(); }

            function applyFilters() {
                const searchTerm = normalize(searchInput?.value);
                const activeOnly = Boolean(activeOnlyToggle?.checked);
                let visible = 0;

                document.querySelectorAll('[data-pricing-row]').forEach((row) => {
                    const quantity = Number(row.querySelector('[data-quantity]')?.value || 0);
                    const matchesSearch = !searchTerm || normalize(row.dataset.searchText).includes(searchTerm);
                    const matchesActive = !activeOnly || quantity > 0;
                    const isVisible = matchesSearch && matchesActive;

                    row.classList.toggle('is-hidden-by-filter', !isVisible);
                    if (isVisible) visible += 1;
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
            activeOnlyToggle?.addEventListener('change', applyFilters);
            recalculate();
        })();
    </script>
@endsection
