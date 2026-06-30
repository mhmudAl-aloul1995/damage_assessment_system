@extends('layouts.app')

@section('title', 'المساعدة النقدية لإصلاح المأوى الطارئ (HEKS)')
@section('pageName', 'لوحة فلترة HEKS')

@php
    $filterSelects = [
        'governorate' => ['label' => 'المحافظة', 'options' => $filterOptions['governorates']],
        'area' => ['label' => 'المنطقة/التجمع', 'options' => $filterOptions['areas']],
        'damage_status' => ['label' => 'تقييم ضرر المأوى', 'options' => $filterOptions['damageStatuses']],
        'unit_type' => ['label' => 'نوع الوحدة السكنية', 'options' => $filterOptions['unitTypes']],
        'occupancy_status' => ['label' => 'حالة الإشغال', 'options' => $filterOptions['occupancyStatuses']],
        'household_head_gender' => ['label' => 'جنس رب الأسرة', 'options' => $filterOptions['headGenders']],
        'displacement_status' => ['label' => 'حالة النزوح', 'options' => $filterOptions['displacementStatuses']],
        'classification' => ['label' => 'تصنيف الأولوية', 'options' => $filterOptions['classifications']],
        'income_source' => ['label' => 'مصدر دخل ثابت', 'options' => $filterOptions['incomeSources']],
        'food_aid' => ['label' => 'الاعتماد على مساعدات غذائية', 'options' => $filterOptions['foodAidOptions']],
        'overcrowding' => ['label' => 'الاكتظاظ/مساحة الفرد', 'options' => $filterOptions['overcrowdingOptions']],
        'privacy' => ['label' => 'الخصوصية والفصل', 'options' => $filterOptions['privacyOptions']],
    ];

    $flagFilters = [
        'has_disability' => 'أشخاص ذوي إعاقة',
        'war_injury' => 'مصابون في الحرب',
        'chronic_disease' => 'أمراض مزمنة',
        'uxo_risk' => 'خطر UXO / ERW',
        'unsafe_structure' => 'وحدة غير آمنة إنشائياً',
        'documents_ready' => 'أوراق ثبوتية متوفرة',
        'bank_account' => 'حساب بنكي متوفر',
    ];

    $distributionCards = [
        'تقييم ضرر المأوى' => $damageDistribution,
        'حالة الإشغال' => $occupancyDistribution,
        'جنس رب الأسرة' => $genderDistribution,
        'حالة النزوح' => $displacementDistribution,
        'تصنيف الأولوية' => $classificationDistribution,
    ];
@endphp

@section('content')
    @include('heks::partials.nav')

    <form method="GET" action="{{ route('heks.dashboard') }}" class="card card-flush mb-6">
        <div class="card-header">
            <div>
                <h3 class="card-title">فلاتر المستفيدين</h3>
                <div class="text-muted">اختر فلتر أو أكثر لعرض المستفيدين والمؤشرات حسب نفس المعايير.</div>
            </div>
            <div class="card-toolbar d-flex gap-2">
                <a href="{{ route('heks.dashboard') }}" class="btn btn-sm btn-light">مسح</a>
                <button class="btn btn-sm btn-primary">تطبيق</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4">
                @foreach ($filterSelects as $name => $select)
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <label class="form-label">{{ $select['label'] }}</label>
                        <select name="{{ $name }}" class="form-select">
                            <option value="">الكل</option>
                            @foreach ($select['options'] as $option)
                                <option value="{{ $option }}" @selected($filters[$name] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <label class="form-label">تاريخ الزيارة من</label>
                    <input type="date" name="visit_from" class="form-control" value="{{ $filters['visit_from'] }}">
                </div>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <label class="form-label">تاريخ الزيارة إلى</label>
                    <input type="date" name="visit_to" class="form-control" value="{{ $filters['visit_to'] }}">
                </div>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <label class="form-label">عدد الأفراد من</label>
                    <input type="number" min="0" name="household_min" class="form-control" value="{{ $filters['household_min'] }}">
                </div>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <label class="form-label">عدد الأفراد إلى</label>
                    <input type="number" min="0" name="household_max" class="form-control" value="{{ $filters['household_max'] }}">
                </div>
            </div>

            <div class="separator my-5"></div>

            <div class="d-flex flex-wrap gap-4">
                @foreach ($flagFilters as $name => $label)
                    <label class="form-check form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" @checked($filters[$name])>
                        <span class="form-check-label">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </form>

    <div class="card card-flush mb-6">
        <div class="card-header">
            <h3 class="card-title">مسار الحالات حسب الفلاتر</h3>
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
            'beneficiaries' => 'الحالات المطابقة',
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

    <div class="row g-5 mb-6">
        @foreach ([
            'إجمالي الأفراد' => $populationSummary['household_members'],
            'أسر تعيلها إناث' => $populationSummary['female_heads'],
            'سيدات مرضعات' => $populationSummary['lactating_women'],
            'ذوو إعاقة' => $populationSummary['disabled_people'],
            'أمراض مزمنة' => $populationSummary['chronic_people'],
            'مصابون في الحرب' => $populationSummary['war_injured_people'],
        ] as $label => $value)
            <div class="col-xl-2 col-md-4 col-6">
                <div class="border rounded p-4 h-100">
                    <div class="text-muted">{{ $label }}</div>
                    <div class="fs-2 fw-bold">{{ number_format($value) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-5 mb-6">
        @foreach ($distributionCards as $title => $distribution)
            <div class="col-xl-4 col-md-6">
                <div class="card card-flush h-100">
                    <div class="card-header"><h3 class="card-title">{{ $title }}</h3></div>
                    <div class="card-body">
                        @php($max = max(1, (int) $distribution->max()))
                        @forelse ($distribution as $label => $count)
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold">{{ $label }}</span>
                                    <span class="text-muted">{{ $count }}</span>
                                </div>
                                <div class="progress h-8px">
                                    <div class="progress-bar bg-primary" style="width: {{ max(6, round(((int) $count / $max) * 100)) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted">لا توجد بيانات كافية.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-xl-4 col-md-6">
            <div class="card card-flush h-100">
                <div class="card-header"><h3 class="card-title">توزيع العمل على المهندسين</h3></div>
                <div class="card-body">
                    @forelse ($engineerWorkload as $engineerName => $engineer)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                            <div>
                                <div class="fw-bold">{{ $engineerName }}</div>
                                <div class="text-muted small">{{ number_format((float) $engineer['contract_total'], 2) }} ILS عقود</div>
                            </div>
                            <span class="badge badge-light-primary">{{ $engineer['cases_count'] }}</span>
                        </div>
                    @empty
                        <div class="text-muted">لا توجد مجموعات عمل ضمن الفلاتر الحالية.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header">
            <div>
                <h3 class="card-title">المستفيدون المطابقون</h3>
                <div class="text-muted">يتم عرض أول 50 حالة من أصل {{ number_format($filteredCount) }} حالة مطابقة.</div>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                <tr class="fw-bold text-muted">
                    <th>الكود</th>
                    <th>المستفيد</th>
                    <th>المكان</th>
                    <th>تاريخ الزيارة</th>
                    <th>الضرر</th>
                    <th>الإشغال</th>
                    <th>النزوح</th>
                    <th>التصنيف</th>
                    <th>المنحة</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($filteredBeneficiaries as $beneficiary)
                    <tr>
                        <td class="fw-bold">{{ $beneficiary->code }}</td>
                        <td>
                            <div class="fw-semibold">{{ $beneficiary->name ?? '-' }}</div>
                            <div class="text-muted small">{{ $beneficiary->identity_number ?? '-' }}</div>
                        </td>
                        <td>{{ $beneficiary->governorate ?? '-' }} / {{ $beneficiary->area ?? '-' }}</td>
                        <td>{{ $beneficiary->visit_date?->format('Y-m-d') ?? '-' }}</td>
                        <td>{{ $beneficiary->damage_status ?? '-' }}</td>
                        <td>{{ $beneficiary->occupancy_status ?? '-' }}</td>
                        <td>{{ $beneficiary->displacement_status ?? '-' }}</td>
                        <td>{{ $beneficiary->scores->first()?->classification ?? '-' }}</td>
                        <td>{{ $beneficiary->grant_amount ? number_format((float) $beneficiary->grant_amount, 2) : '-' }}</td>
                        <td><a class="btn btn-sm btn-light-primary" href="{{ route('heks.beneficiaries.edit', $beneficiary) }}">فتح</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted">لا توجد حالات مطابقة للفلاتر الحالية.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
