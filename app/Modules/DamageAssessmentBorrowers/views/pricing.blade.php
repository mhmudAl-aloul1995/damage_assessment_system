@extends('layouts.app')

@section('title', 'تسعير مستفيد قرض بنك التنمية الإسلامي')
@section('pageName', 'تسعير المستفيد')

@section('content')
    <style>
        .borrower-pricing-page .pricing-number {
            direction: ltr;
            text-align: right;
            font-variant-numeric: tabular-nums;
            unicode-bidi: plaintext;
        }

        .borrower-pricing-page .pricing-table {
            min-width: 1120px;
            table-layout: fixed;
        }

        .borrower-pricing-page .pricing-page-header {
            background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.08), rgba(var(--bs-success-rgb), 0.05));
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.75rem;
        }

        .borrower-pricing-page .pricing-summary-card {
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.75rem;
            height: 100%;
            padding: 1.2rem;
        }

        .borrower-pricing-page .pricing-exchange-panel {
            background: var(--bs-gray-100);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.75rem;
            padding: 1.25rem;
        }

        .borrower-pricing-page .pricing-tools {
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.75rem;
            padding: 1rem;
        }

        .borrower-pricing-page .pricing-table th,
        .borrower-pricing-page .pricing-table td {
            min-width: 0 !important;
        }

        .borrower-pricing-page .pricing-table thead th {
            background: var(--bs-body-bg);
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .borrower-pricing-page .pricing-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .borrower-pricing-page .pricing-table tbody tr.is-priced-row {
            background: rgba(var(--bs-success-rgb), 0.035);
        }

        .borrower-pricing-page .pricing-table tbody tr.is-hidden-by-filter {
            display: none;
        }

        .borrower-pricing-page .pricing-col-code {
            width: 86px;
        }

        .borrower-pricing-page .pricing-col-item {
            width: 37%;
        }

        .borrower-pricing-page .pricing-col-unit {
            width: 88px;
        }

        .borrower-pricing-page .pricing-col-money {
            width: 104px;
        }

        .borrower-pricing-page .pricing-col-quantity {
            width: 88px;
        }

        .borrower-pricing-page .pricing-item-description {
            line-height: 1.55;
            max-width: 100%;
            overflow-wrap: anywhere;
        }

        .borrower-pricing-page .pricing-table-wrap {
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.75rem;
            max-height: calc(100vh - 18rem);
            overflow: auto;
        }

        .borrower-pricing-page .pricing-save-bar {
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.75rem;
            bottom: 1rem;
            box-shadow: 0 0.65rem 1.75rem rgba(15, 23, 42, 0.08);
            position: sticky;
            z-index: 2;
        }
    </style>

    @php
        $savedItemsCount = $borrower->boqItems->filter(fn ($item) => (float) $item->quantity > 0)->count();
        $catalogItemsCount = $pricingRows->count();
        $exchangeRate = old('exchange_rate', $borrower->exchange_rate ?: 3.2);
    @endphp

    <div class="borrower-pricing-page">
        <div class="pricing-page-header p-5 mb-6">
            <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-5">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge badge-light-primary">تسعير BOQ</span>
                        <span class="badge badge-light">{{ $borrower->form_number ?: 'بدون رقم نموذج' }}</span>
                    </div>
                    <h2 class="fw-bold mb-2">{{ $borrower->borrower_name }}</h2>
                    <div class="text-muted fs-6">{{ $borrower->borrower_id_number ?: '-' }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('damage-assessment-borrowers.show', $borrower) }}" class="btn btn-light">
                        بيانات المستفيد
                    </a>
                    <a href="{{ route('damage-assessment-borrowers.index') }}" class="btn btn-light-primary">
                        رجوع
                    </a>
                    <button type="submit" form="borrowerPricingForm" class="btn btn-primary">
                        حفظ التسعير
                    </button>
                </div>
            </div>
        </div>

        <div class="card card-flush">
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="row g-5 mb-8">
                <div class="col-md-4">
                    <div class="pricing-summary-card">
                        <div class="text-muted fs-7 mb-1">الإجمالي بالدولار</div>
                        <div class="fs-2 fw-bold text-primary" id="pricingGrandTotal">
                            {{ number_format((float) $borrower->boq_total_usd, 2) }} $
                        </div>
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
                        <div class="text-muted fs-7 mb-1">الإجمالي بالشيكل</div>
                        <div class="fs-2 fw-bold text-success" id="pricingGrandTotalIls">
                            {{ number_format((float) $borrower->boq_total_ils, 2) }} ILS
                        </div>
                        <div class="text-muted fs-8 mt-2">يتغير مباشرة حسب سعر الصرف</div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('damage-assessment-borrowers.pricing.update', $borrower) }}" id="borrowerPricingForm">
                @csrf
                @method('PUT')

                <div class="pricing-exchange-panel mb-5">
                    <div class="row g-4 align-items-end">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label fw-semibold">سعر صرف الدولار / شيكل</label>
                            <input type="text" inputmode="decimal" name="exchange_rate" value="{{ $exchangeRate }}" class="form-control form-control-lg pricing-number" id="exchangeRateInput" dir="ltr">
                        </div>
                        <div class="col-lg-8">
                            <div class="alert alert-primary mb-0 py-3">
                                تغيير سعر الصرف هنا يعيد احتساب قيمة الشيكل لكل استبيانات المقترضين، مع بقاء قيم الدولار كما هي.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pricing-tools d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 mb-4">
                    <div class="d-flex flex-column flex-md-row gap-3 flex-grow-1">
                        <input type="search" class="form-control" id="pricingSearchInput" placeholder="بحث بالكود أو اسم البند">
                        <label class="form-check form-switch form-check-custom form-check-solid align-items-center">
                            <input class="form-check-input" type="checkbox" value="1" id="pricingActiveOnlyToggle">
                            <span class="form-check-label text-muted">عرض البنود المسعّرة فقط</span>
                        </label>
                    </div>
                    <div class="text-muted fs-7">
                        الظاهر الآن: <span class="fw-bold text-gray-800" id="pricingVisibleCount">{{ $catalogItemsCount }}</span> بند
                    </div>
                </div>

                <div class="table-responsive pricing-table-wrap">
                    <table class="table table-row-dashed align-middle pricing-table" id="pricingTable">
                        <colgroup>
                            <col class="pricing-col-code">
                            <col class="pricing-col-item">
                            <col class="pricing-col-unit">
                            <col class="pricing-col-money">
                            <col class="pricing-col-money">
                            <col class="pricing-col-quantity">
                            <col class="pricing-col-money">
                            <col class="pricing-col-money">
                        </colgroup>
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-90px">الكود</th>
                                <th class="min-w-350px">البند</th>
                                <th class="min-w-90px">الوحدة</th>
                                <th class="min-w-140px">سعر الوحدة $</th>
                                <th class="min-w-140px">سعر الوحدة ILS</th>
                                <th class="min-w-120px">الكمية</th>
                                <th class="min-w-140px">الإجمالي $</th>
                                <th class="min-w-140px">الإجمالي ILS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pricingRows as $index => $row)
                                <tr data-pricing-row data-search-text="{{ Str::lower($row['item_code'].' '.$row['description'].' '.$row['unit']) }}">
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][catalog_item_id]" value="{{ $row['catalog_item_id'] }}">
                                        <input type="hidden" name="items[{{ $index }}][source_column]" value="{{ $row['source_column'] }}">
                                        <input type="hidden" name="items[{{ $index }}][source_key]" value="{{ $row['source_key'] }}">
                                        <input type="hidden" name="items[{{ $index }}][description]" value="{{ $row['description'] }}">
                                        <input type="hidden" name="items[{{ $index }}][sort_order]" value="{{ $row['sort_order'] }}">
                                        <input type="text" name="items[{{ $index }}][item_code]" value="{{ $row['item_code'] }}" class="form-control form-control-sm form-control-solid">
                                    </td>
                                    <td>
                                        <div class="fw-semibold pricing-item-description">{{ $row['description'] }}</div>
                                    </td>
                                    <td>
                                        <input type="text" name="items[{{ $index }}][unit]" value="{{ $row['unit'] }}" class="form-control form-control-sm form-control-solid">
                                    </td>
                                    <td>
                                        <input type="text" inputmode="decimal" name="items[{{ $index }}][unit_price]" value="{{ $row['unit_price'] }}" class="form-control form-control-sm pricing-number" data-unit-price dir="ltr">
                                    </td>
                                    <td>
                                        <input type="text" inputmode="decimal" name="items[{{ $index }}][unit_price_ils]" value="{{ $row['unit_price_ils'] }}" class="form-control form-control-sm pricing-number form-control-solid" data-unit-price-ils readonly dir="ltr">
                                    </td>
                                    <td>
                                        <input type="text" inputmode="decimal" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" class="form-control form-control-sm pricing-number" data-quantity dir="ltr">
                                    </td>
                                    <td class="fw-bold pricing-number" data-row-total>{{ number_format((float) $row['total_price'], 2) }}</td>
                                    <td class="fw-bold pricing-number text-success" data-row-total-ils>{{ number_format((float) $row['total_price_ils'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-10">
                                        لا توجد بنود BOQ بعد. ارفع ملف أسعار BOQ من شاشة الاستيراد أولًا.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="pricing-save-bar d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 mt-5">
                    <div>
                        <div class="fw-bold">جاهز للحفظ</div>
                        <div class="text-muted fs-7">سيتم حفظ كميات وأسعار هذا المستفيد وتطبيق سعر الصرف على كل الاستبيانات.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        حفظ التسعير
                    </button>
                </div>
            </form>
        </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const formatter = new Intl.NumberFormat(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
            const table = document.getElementById('pricingTable');
            const grandTotal = document.getElementById('pricingGrandTotal');
            const grandTotalIls = document.getElementById('pricingGrandTotalIls');
            const activeCount = document.getElementById('pricingActiveCount');
            const visibleCount = document.getElementById('pricingVisibleCount');
            const exchangeRateInput = document.getElementById('exchangeRateInput');
            const searchInput = document.getElementById('pricingSearchInput');
            const activeOnlyToggle = document.getElementById('pricingActiveOnlyToggle');

            function normalize(value) {
                return String(value || '').trim().toLowerCase();
            }

            function applyFilters() {
                const searchTerm = normalize(searchInput?.value);
                const activeOnly = Boolean(activeOnlyToggle?.checked);
                let visible = 0;

                document.querySelectorAll('[data-pricing-row]').forEach((row) => {
                    const quantity = Number(row.querySelector('[data-quantity]')?.value || 0);
                    const matchesSearch = ! searchTerm || normalize(row.dataset.searchText).includes(searchTerm);
                    const matchesActive = ! activeOnly || quantity > 0;
                    const isVisible = matchesSearch && matchesActive;

                    row.classList.toggle('is-hidden-by-filter', ! isVisible);
                    if (isVisible) {
                        visible += 1;
                    }
                });

                if (visibleCount) {
                    visibleCount.textContent = formatter.format(visible);
                }
            }

            function recalculate() {
                let total = 0;
                let totalIls = 0;
                let active = 0;
                const exchangeRate = Number(exchangeRateInput?.value || 0);
                document.querySelectorAll('[data-pricing-row]').forEach((row) => {
                    const unitPrice = Number(row.querySelector('[data-unit-price]')?.value || 0);
                    const quantity = Number(row.querySelector('[data-quantity]')?.value || 0);
                    const unitPriceIls = unitPrice * exchangeRate;
                    const rowTotal = unitPrice * quantity;
                    const rowTotalIls = unitPriceIls * quantity;
                    total += rowTotal;
                    totalIls += rowTotalIls;
                    if (quantity > 0) {
                        active += 1;
                    }
                    row.classList.toggle('is-priced-row', quantity > 0);
                    row.querySelector('[data-unit-price-ils]').value = formatter.format(unitPriceIls).replace(/,/g, '');
                    row.querySelector('[data-row-total]').textContent = formatter.format(rowTotal);
                    row.querySelector('[data-row-total-ils]').textContent = formatter.format(rowTotalIls);
                });
                grandTotal.textContent = `${formatter.format(total)} $`;
                grandTotalIls.textContent = `${formatter.format(totalIls)} ILS`;
                if (activeCount) {
                    activeCount.textContent = formatter.format(active);
                }
                applyFilters();
            }

            table?.addEventListener('input', (event) => {
                if (event.target.matches('[data-unit-price], [data-quantity]')) {
                    recalculate();
                }
            });
            exchangeRateInput?.addEventListener('input', recalculate);
            searchInput?.addEventListener('input', applyFilters);
            activeOnlyToggle?.addEventListener('change', applyFilters);

            recalculate();
        })();
    </script>
@endpush
