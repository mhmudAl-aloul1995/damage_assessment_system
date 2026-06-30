@extends('layouts.app')

@section('title', 'المساعدة النقدية لإصلاح المأوى الطارئ (HEKS)')
@section('pageName', 'نظرة عامة على HEKS')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush mb-6">
        <div class="card-header">
            <h3 class="card-title">مسار الحالات</h3>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3">
                @foreach ($pipeline as $stage)
                    <div class="border rounded p-4 flex-grow-1 min-w-150px">
                        <div class="text-gray-600 fw-semibold">{{ $stage['label'] }}</div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-2 fw-bold">{{ number_format($stage['count']) }}</span>
                            <span class="badge badge-light-{{ $stage['tone'] }}">حالة</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-5 mb-6">
        @foreach ([
            'beneficiaries' => 'إجمالي الحالات',
            'selected' => 'المختارون',
            'payments' => 'سجلات الدفعات',
            'follow_ups' => 'زيارات المتابعة',
            'attachments' => 'المرفقات',
            'grant_total' => 'إجمالي المنح ILS',
        ] as $key => $label)
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-gray-500 fw-semibold">{{ $label }}</div>
                        <div class="fs-2hx fw-bold">{{ is_float($stats[$key]) ? number_format($stats[$key], 2) : number_format($stats[$key]) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-5">
        <div class="col-xl-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">توزيع العمل على المهندسين</h3></div>
                <div class="card-body">
                    @forelse ($engineerWorkload as $engineer)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                            <div>
                                <div class="fw-bold">{{ $engineer->engineer_name }}</div>
                                <div class="text-muted small">{{ number_format((float) $engineer->contract_total, 2) }} ILS عقود</div>
                            </div>
                            <span class="badge badge-light-primary">{{ $engineer->cases_count }}</span>
                        </div>
                    @empty
                        <div class="text-muted">لم يتم استيراد مجموعات العمل بعد.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">حالة الدفعات</h3></div>
                <div class="card-body">
                    @forelse ($paymentStatusDistribution as $status => $count)
                        <div class="d-flex justify-content-between border-bottom py-3">
                            <span class="fw-semibold">{{ match ((string) $status) {
                                'paid_30' => 'تمت الدفعة الأولى',
                                'paid_80' => 'تمت الدفعة الأولى والثانية',
                                'paid_100' => 'مكتمل الدفع',
                                default => 'قيد التجهيز',
                            } }}</span>
                            <span class="badge badge-light-success">{{ $count }}</span>
                        </div>
                    @empty
                        <div class="text-muted">لم يتم استيراد الدفعات بعد.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">آخر عمليات الاستيراد</h3></div>
                <div class="card-body">
                    @forelse ($latestImports as $import)
                        <div class="border-bottom py-3">
                            <div class="fw-semibold text-truncate">{{ $import->filename }}</div>
                            <div class="text-muted small">{{ $import->type }} · {{ $import->total_rows }} صف · {{ $import->created_at?->format('Y-m-d H:i') }}</div>
                        </div>
                    @empty
                        <div class="text-muted">لا توجد عمليات استيراد بعد.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
