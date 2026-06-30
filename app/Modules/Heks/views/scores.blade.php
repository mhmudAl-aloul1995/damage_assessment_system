@extends('layouts.app')

@section('title', 'التقييم والدرجات')
@section('pageName', 'التقييم والدرجات')

@section('content')
    @include('heks::partials.nav')

    <div class="row g-4 mb-6">
        @foreach ([
            'إجمالي سجلات التقييم' => number_format((float) $scoreSummary['total']),
            'متوسط الاجتماعي' => $scoreSummary['average_social'] !== null ? number_format((float) $scoreSummary['average_social'], 2) : '-',
            'متوسط الفني' => $scoreSummary['average_technical'] !== null ? number_format((float) $scoreSummary['average_technical'], 2) : '-',
            'متوسط الكلي' => $scoreSummary['average_total'] !== null ? number_format((float) $scoreSummary['average_total'], 2) : '-',
        ] as $label => $value)
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">{{ $label }}</div>
                        <div class="fs-2 fw-bold">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-flush mb-6">
        <div class="card-header">
            <div>
                <h3 class="card-title">توزيع التصنيف</h3>
                <div class="text-muted">التصنيف القادم من عمود التصنيف في شيت Scoring-Heks Final.</div>
            </div>
        </div>
        <div class="card-body d-flex flex-wrap gap-3">
            @forelse ($classifications as $classification => $count)
                <span class="badge badge-light-primary fs-7 px-4 py-3">{{ $classification }}: {{ $count }}</span>
            @empty
                <span class="text-muted">لا توجد تصنيفات محفوظة بعد.</span>
            @endforelse
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header">
            <div>
                <h3 class="card-title">سجلات التقييم</h3>
                <div class="text-muted">يعرض الاجتماعي، الفني، التقييم الكلي، التصنيف، قيمة التدخل والدفعات كما وردت من ملف HEKS.</div>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                <tr class="fw-bold text-muted">
                    <th>الكود</th>
                    <th>المستفيد</th>
                    <th>المصدر</th>
                    <th>اجتماعي</th>
                    <th>فني</th>
                    <th>كلي</th>
                    <th>التصنيف</th>
                    <th>Intervention (ILS)</th>
                    <th>الدفعات</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($scores as $score)
                    <tr>
                        <form method="POST" action="{{ route('heks.scores.update', $score) }}">
                            @csrf
                            @method('PUT')
                            <td class="fw-bold">{{ $score->beneficiary?->code }}</td>
                            <td>
                                <div class="fw-semibold">{{ $score->beneficiary?->name ?? '-' }}</div>
                                <div class="text-muted small">{{ $score->beneficiary?->identity_number ?? '-' }}</div>
                            </td>
                            <td class="text-muted">{{ $score->source }}</td>
                            <td><input name="social_score" class="form-control form-control-sm" value="{{ $score->social_score }}" aria-label="Social score"></td>
                            <td><input name="technical_score" class="form-control form-control-sm" value="{{ $score->technical_score }}" aria-label="Technical score"></td>
                            <td><input name="total_score" class="form-control form-control-sm fw-bold" value="{{ $score->total_score }}" aria-label="Total score"></td>
                            <td><input name="classification" class="form-control form-control-sm" value="{{ $score->classification }}" aria-label="Classification"></td>
                            <td><input name="grant_amount" class="form-control form-control-sm" value="{{ $score->grant_amount }}" aria-label="Intervention amount"></td>
                            <td class="min-w-150px">
                                <input name="payment_1" class="form-control form-control-sm mb-2" value="{{ $score->payment_1 }}" placeholder="دفعة 1" aria-label="Payment 1">
                                <input name="payment_2" class="form-control form-control-sm mb-2" value="{{ $score->payment_2 }}" placeholder="دفعة 2" aria-label="Payment 2">
                                <input name="payment_3" class="form-control form-control-sm" value="{{ $score->payment_3 }}" placeholder="دفعة 3" aria-label="Payment 3">
                            </td>
                            <td><button class="btn btn-sm btn-light-primary">حفظ</button></td>
                        </form>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted">لا توجد درجات بعد.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $scores->links() }}
        </div>
    </div>
@endsection
