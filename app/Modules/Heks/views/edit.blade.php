@extends('layouts.app')

@section('title', 'تفاصيل مستفيد HEKS')
@section('pageName', $beneficiary->code)

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush mb-6">
        <div class="card-header">
            <div>
                <h3 class="card-title">{{ $beneficiary->code }} - {{ $beneficiary->name }}</h3>
                <div class="text-muted">
                    {{ $beneficiary->identity_number ?? '-' }} · {{ $beneficiary->phone ?? '-' }} · {{ $beneficiary->field_engineer ?? '-' }}
                </div>
            </div>
            <div class="card-toolbar d-flex gap-2">
                <span class="badge {{ $beneficiary->is_selected ? 'badge-light-success' : 'badge-light' }}">
                    {{ $beneficiary->is_selected ? 'مختار ضمن 125' : 'تقييم أولي' }}
                </span>
                @if ($beneficiary->payment_status)
                    <span class="badge badge-light-warning">
                        {{ match ($beneficiary->payment_status) {
                            'paid_30' => 'دفعة أولى',
                            'paid_80' => 'دفعتان',
                            'paid_100' => 'مدفوع كامل',
                            default => 'قيد الدفع',
                        } }}
                    </span>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4">
                @foreach ([
                    'المنحة' => $beneficiary->grant_amount ? number_format((float) $beneficiary->grant_amount, 2) : '-',
                    'الدفعة 30%' => $beneficiary->payment_1 ? number_format((float) $beneficiary->payment_1, 2) : '-',
                    'الدفعة 50%' => $beneficiary->payment_2 ? number_format((float) $beneficiary->payment_2, 2) : '-',
                    'الدفعة 20%' => $beneficiary->payment_3 ? number_format((float) $beneficiary->payment_3, 2) : '-',
                    'المحافظة' => $beneficiary->governorate ?? '-',
                    'المنطقة' => $beneficiary->area ?? '-',
                    'حالة الضرر' => $beneficiary->damage_status ?? '-',
                    'حالة الإشغال' => $beneficiary->occupancy_status ?? '-',
                ] as $label => $value)
                    <div class="col-xl-3 col-md-4 col-6">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fw-bold">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('heks.beneficiaries.update', $beneficiary) }}" class="card card-flush mb-6">
        @csrf
        @method('PUT')
        <div class="card-header"><h3 class="card-title">تعديل البيانات الأساسية</h3></div>
        <div class="card-body row g-4">
            @foreach ([
                'name' => 'اسم المستفيد',
                'identity_number' => 'رقم الهوية',
                'phone' => 'رقم التواصل',
                'alternate_phone' => 'رقم بديل',
                'field_engineer' => 'المهندس المسؤول',
                'visit_date' => 'تاريخ الزيارة',
                'governorate' => 'المحافظة',
                'area' => 'المنطقة',
                'displacement_status' => 'حالة النزوح',
                'occupancy_status' => 'حالة الإشغال',
                'damage_status' => 'حالة الضرر',
                'grant_amount' => 'المنحة',
                'payment_1' => 'دفعة 30%',
                'payment_2' => 'دفعة 50%',
                'payment_3' => 'دفعة 20%',
            ] as $field => $label)
                <div class="col-md-4">
                    <label class="form-label">{{ $label }}</label>
                    <input name="{{ $field }}" class="form-control" value="{{ old($field, $field === 'visit_date' ? $beneficiary->{$field}?->format('Y-m-d') : $beneficiary->{$field}) }}">
                </div>
            @endforeach
            @foreach (['address' => 'العنوان', 'social_notes' => 'ملاحظات اجتماعية', 'engineer_notes' => 'ملاحظات هندسية', 'recommendations' => 'التوصيات'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label">{{ $label }}</label>
                    <textarea name="{{ $field }}" class="form-control" rows="3">{{ old($field, $beneficiary->{$field}) }}</textarea>
                </div>
            @endforeach
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-primary">حفظ البيانات الأساسية</button>
        </div>
    </form>

    <div class="row g-5 mb-6">
        <div class="col-xl-6">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">التقييم والدرجات</h3></div>
                <div class="card-body table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>المصدر</th><th>اجتماعي</th><th>فني</th><th>نهائي</th><th>التصنيف</th><th>المنحة</th></tr></thead>
                        <tbody>
                        @forelse ($beneficiary->scores as $score)
                            <tr>
                                <td>{{ $score->source ?? '-' }}</td>
                                <td>{{ $score->social_score ?? '-' }}</td>
                                <td>{{ $score->technical_score ?? '-' }}</td>
                                <td>{{ $score->total_score ?? '-' }}</td>
                                <td>{{ $score->classification ?? '-' }}</td>
                                <td>{{ $score->grant_amount ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">لا توجد درجات.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">معايير ونتائج التقييم</h3></div>
                <div class="card-body table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>المعيار</th><th>القيمة</th><th>المصدر</th></tr></thead>
                        <tbody>
                        @forelse ($beneficiary->labels as $label)
                            <tr>
                                <td class="fw-semibold">{{ $label->label_key }}</td>
                                <td>{{ $label->label_value ?? '-' }}</td>
                                <td class="text-muted">{{ $label->source ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">لا توجد معايير.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-6">
        <div class="col-xl-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">الدفعات</h3></div>
                <div class="card-body table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>المصدر</th><th>30%</th><th>50%</th><th>20%</th></tr></thead>
                        <tbody>
                        @forelse ($beneficiary->payments as $payment)
                            <tr>
                                <td>{{ $payment->source ?? '-' }}</td>
                                <td>{{ $payment->payment_1_amount ?? '-' }}</td>
                                <td>{{ $payment->payment_2_amount ?? '-' }}</td>
                                <td>{{ $payment->payment_3_amount ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">لا توجد دفعات.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">التوزيع والمتابعة</h3></div>
                <div class="card-body">
                    @forelse ($beneficiary->workAssignments as $assignment)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="fw-bold">{{ $assignment->engineer_name ?? '-' }}</div>
                            <div class="text-muted small">{{ $assignment->source }}</div>
                            <div>قيمة العقد: {{ $assignment->contract_amount_ils ? number_format((float) $assignment->contract_amount_ils, 2) : '-' }} ILS</div>
                        </div>
                    @empty
                        <div class="text-muted">لا يوجد توزيع عمل.</div>
                    @endforelse

                    @forelse ($beneficiary->followUps as $followUp)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="fw-bold">{{ $followUp->visit_date?->format('Y-m-d') ?? '-' }} · {{ $followUp->engineer_name ?? '-' }}</div>
                            <div>{{ $followUp->working_condition ?? '-' }}</div>
                            <div class="text-muted small">{{ $followUp->engineer_recommendations }}</div>
                        </div>
                    @empty
                        <div class="text-muted">لا توجد متابعات.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">المرفقات والصور</h3></div>
                <div class="card-body">
                    @forelse ($beneficiary->attachments as $attachment)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="fw-bold text-truncate">{{ $attachment->filename ?? '-' }}</div>
                            <div class="text-muted small">{{ $attachment->attachment_type ?? $attachment->source }}</div>
                            @if ($attachment->url)
                                <a class="btn btn-sm btn-light mt-2" href="{{ $attachment->url }}" target="_blank" rel="noopener">فتح المرفق</a>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">لا توجد مرفقات مرتبطة.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header"><h3 class="card-title">كل بيانات الشيتات المستوردة</h3></div>
        <div class="card-body">
            @forelse ($rawDataSections as $source => $values)
                <div class="mb-6">
                    <h4 class="fs-6 fw-bold mb-3">{{ $source }}</h4>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle">
                            <tbody>
                            @foreach ($values as $key => $value)
                                @continue(str_starts_with((string) $key, '_') && ! in_array($key, ['_submission__uuid', '_submission___version__'], true))
                                <tr>
                                    <th class="w-300px text-muted">{{ $key }}</th>
                                    <td>{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-muted">لا توجد بيانات خام محفوظة لهذه الحالة.</div>
            @endforelse
        </div>
    </div>
@endsection
