@extends('layouts.app')

@section('title', 'BOQ زيارة HEKS')
@section('pageName', 'جدول كميات زيارة المتابعة')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush mb-6">
        <div class="card-header">
            <div>
                <h3 class="card-title">جدول كميات زيارة المتابعة</h3>
                <div class="text-muted">
                    {{ $beneficiary?->code ?? $followUp->code }} - {{ $beneficiary?->name ?? '-' }}
                    · زيارة {{ $followUp->visit_number ?? '-' }}
                    · {{ $followUp->visit_date?->format('Y-m-d') ?? '-' }}
                </div>
            </div>
            <div class="card-toolbar d-flex flex-wrap gap-2">
                @if ($beneficiary)
                    <a href="{{ route('heks.beneficiaries.pricing', $beneficiary) }}" class="btn btn-light-primary">BOQ الأساسي</a>
                    <a href="{{ route('heks.beneficiaries.edit', $beneficiary) }}" class="btn btn-light">بيانات المستفيد</a>
                @endif
                <a href="{{ route('heks.follow-ups') }}" class="btn btn-light">رجوع للمتابعات</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4 mb-6">
                <div class="col-md-3 col-6">
                    <div class="border rounded p-4 h-100">
                        <div class="text-muted small">عدد البنود</div>
                        <div class="fs-2 fw-bold">{{ number_format($followUp->boqItems->count()) }}</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="border rounded p-4 h-100">
                        <div class="text-muted small">إجمالي BOQ الزيارة</div>
                        <div class="fs-2 fw-bold text-success">{{ number_format($boqTotal, 2) }} ILS</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="border rounded p-4 h-100">
                        <div class="text-muted small">المنجز في المتابعة</div>
                        <div class="fs-2 fw-bold">{{ $followUp->completed_amount_ils ? number_format((float) $followUp->completed_amount_ils, 2) : '-' }} ILS</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="border rounded p-4 h-100">
                        <div class="text-muted small">نسبة الإنجاز</div>
                        <div class="fs-2 fw-bold">{{ $followUp->completion_percentage !== null ? number_format((float) $followUp->completion_percentage, 2).'%' : '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead>
                    <tr class="fw-bold text-muted">
                        <th>القسم</th>
                        <th>رقم</th>
                        <th class="min-w-300px">الوصف</th>
                        <th>الوحدة</th>
                        <th>الكمية</th>
                        <th>سعر الوحدة ILS</th>
                        <th>الإجمالي ILS</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($followUp->boqItems as $item)
                        <tr>
                            <td>{{ $item->section ?? '-' }}</td>
                            <td class="fw-bold">{{ $item->item_code ?? '-' }}</td>
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->unit ?? '-' }}</td>
                            <td>{{ number_format((float) $item->quantity, 3) }}</td>
                            <td>{{ number_format((float) $item->unit_price_ils, 2) }}</td>
                            <td class="fw-bold text-success">{{ number_format((float) $item->total_price_ils, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-8">لا توجد بنود جدول كميات مستوردة لهذه الزيارة.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
