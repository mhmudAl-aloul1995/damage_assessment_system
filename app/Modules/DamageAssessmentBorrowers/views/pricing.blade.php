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
    </style>

    @php
        $savedItemsCount = $borrower->boqItems->filter(fn ($item) => (float) $item->quantity > 0)->count();
    @endphp

    <div class="borrower-pricing-page">
    <div class="card card-flush">
        <div class="card-header align-items-center gap-3">
            <div class="card-title">
                <div>
                    <h3 class="fw-bold mb-1">{{ $borrower->borrower_name }}</h3>
                    <div class="text-muted fs-7">
                        {{ $borrower->borrower_id_number ?: '-' }} - {{ $borrower->form_number ?: '-' }}
                    </div>
                </div>
            </div>
            <div class="card-toolbar gap-2">
                <a href="{{ route('damage-assessment-borrowers.index') }}" class="btn btn-light-primary">
                    رجوع
                </a>
                <button type="submit" form="borrowerPricingForm" class="btn btn-primary">
                    حفظ التسعير
                </button>
            </div>
        </div>

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
                    <div class="border rounded p-5 h-100">
                        <div class="text-muted fs-7 mb-1">الإجمالي بالدولار</div>
                        <div class="fs-2 fw-bold text-primary" id="pricingGrandTotal">
                            {{ number_format((float) $borrower->boq_total_usd, 2) }} $
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-5 h-100">
                        <div class="text-muted fs-7 mb-1">عدد البنود المحفوظة</div>
                        <div class="fs-2 fw-bold">{{ $savedItemsCount }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-5 h-100">
                        <div class="text-muted fs-7 mb-1">الإجمالي بالشيكل</div>
                        <div class="fs-2 fw-bold text-success" id="pricingGrandTotalIls">
                            {{ number_format((float) $borrower->boq_total_ils, 2) }} ILS
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('damage-assessment-borrowers.pricing.update', $borrower) }}" id="borrowerPricingForm">
                @csrf
                @method('PUT')

                <div class="row g-5 mb-5">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">سعر صرف الدولار / شيكل</label>
                        <input type="text" inputmode="decimal" name="exchange_rate" value="{{ old('exchange_rate', $borrower->exchange_rate ?: 3.2) }}" class="form-control pricing-number" id="exchangeRateInput" dir="ltr">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle" id="pricingTable">
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
                                <tr data-pricing-row>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][catalog_item_id]" value="{{ $row['catalog_item_id'] }}">
                                        <input type="hidden" name="items[{{ $index }}][source_column]" value="{{ $row['source_column'] }}">
                                        <input type="hidden" name="items[{{ $index }}][source_key]" value="{{ $row['source_key'] }}">
                                        <input type="hidden" name="items[{{ $index }}][description]" value="{{ $row['description'] }}">
                                        <input type="hidden" name="items[{{ $index }}][sort_order]" value="{{ $row['sort_order'] }}">
                                        <input type="text" name="items[{{ $index }}][item_code]" value="{{ $row['item_code'] }}" class="form-control form-control-sm form-control-solid">
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $row['description'] }}</div>
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
            const exchangeRateInput = document.getElementById('exchangeRateInput');

            function recalculate() {
                let total = 0;
                let totalIls = 0;
                const exchangeRate = Number(exchangeRateInput?.value || 0);
                document.querySelectorAll('[data-pricing-row]').forEach((row) => {
                    const unitPrice = Number(row.querySelector('[data-unit-price]')?.value || 0);
                    const quantity = Number(row.querySelector('[data-quantity]')?.value || 0);
                    const unitPriceIls = unitPrice * exchangeRate;
                    const rowTotal = unitPrice * quantity;
                    const rowTotalIls = unitPriceIls * quantity;
                    total += rowTotal;
                    totalIls += rowTotalIls;
                    row.querySelector('[data-unit-price-ils]').value = formatter.format(unitPriceIls).replace(/,/g, '');
                    row.querySelector('[data-row-total]').textContent = formatter.format(rowTotal);
                    row.querySelector('[data-row-total-ils]').textContent = formatter.format(rowTotalIls);
                });
                grandTotal.textContent = `${formatter.format(total)} $`;
                grandTotalIls.textContent = `${formatter.format(totalIls)} ILS`;
            }

            table?.addEventListener('input', (event) => {
                if (event.target.matches('[data-unit-price], [data-quantity]')) {
                    recalculate();
                }
            });
            exchangeRateInput?.addEventListener('input', recalculate);

            recalculate();
        })();
    </script>
@endpush
