@extends('layouts.app')

@section('title', 'إعدادات السكور')
@section('pageName', 'إعدادات السكور')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush mb-6">
        <div class="card-body">
            <form method="GET" action="{{ route('heks.scores') }}" class="row g-4 align-items-end">
                <div class="col-xl-3 col-md-6">
                    <label class="form-label">مرحلة الاستبيان</label>
                    <select name="phase" class="form-select" data-control="select2" data-hide-search="true" onchange="this.form.submit()">
                        @foreach ($phaseOptions as $value => $label)
                            <option value="{{ $value }}" @selected($phase === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label">نوع السكور</label>
                    <select name="component" class="form-select" data-control="select2" data-hide-search="true" onchange="this.form.submit()">
                        @foreach ($componentOptions as $value => $label)
                            <option value="{{ $value }}" @selected($component === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label">المصدر</label>
                    <select name="source" class="form-select" data-control="select2" onchange="this.form.submit()">
                        <option value="">كل المصادر</option>
                        @foreach ($sourceOptions as $sourceOption)
                            <option value="{{ $sourceOption }}" @selected($source === $sourceOption)>{{ $sourceOption }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-3 col-md-6 d-flex gap-2 justify-content-xl-end">
                    <a class="btn btn-light w-100" href="{{ route('heks.scores', ['phase' => $phase]) }}">تصفية</a>
                    @if ($phase === 'phase_2')
                        <button class="btn btn-light-primary w-100" form="copyPhaseTwoWeights">نسخ أوزان الأولى</button>
                    @endif
                </div>
            </form>

            @if ($phase === 'phase_2')
                <form id="copyPhaseTwoWeights" method="POST" action="{{ route('heks.scoring-weights.copy-phase-two') }}">
                    @csrf
                </form>
            @endif
        </div>
    </div>

    <div class="row g-4 mb-6">
        @foreach ([
            'إجمالي القواعد' => number_format((float) $weightSummary['total']),
            'مصادر الأوزان' => number_format((float) $weightSummary['sources']),
            'الأسئلة' => number_format((float) $weightSummary['questions']),
            'خيارات بنقاط' => number_format((float) $weightSummary['option_scores']),
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
        <div class="card-body d-flex flex-wrap gap-3">
            @forelse ($sources as $sourceName => $count)
                @php($sourceTone = $sourceName === 'S-V' ? 'success' : 'primary')
                <span class="badge badge-light-{{ $sourceTone }} fs-7 px-4 py-3">{{ $sourceName }}: {{ $count }}</span>
            @empty
                <span class="text-muted">لا توجد إعدادات محفوظة لهذه المرحلة.</span>
            @endforelse
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header">
            <div>
                <h3 class="card-title">قواعد وأوزان السكور</h3>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                <tr class="fw-bold text-muted">
                    <th class="min-w-125px">النوع</th>
                    <th class="min-w-200px">السؤال</th>
                    <th class="min-w-200px">الخيار / المعيار</th>
                    <th class="min-w-110px">الوزن</th>
                    <th class="min-w-130px">نقاط الخيار</th>
                    <th class="min-w-160px">الفئة</th>
                    <th class="min-w-90px"></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($weights as $weight)
                    @php($formId = 'score-weight-'.$weight->id)
                    @php($isSocial = $weight->source === 'S-V')
                    <tr>
                        <td>
                            <span class="badge badge-light-{{ $isSocial ? 'success' : 'primary' }}">{{ $isSocial ? 'اجتماعي' : 'فني' }}</span>
                            <div class="text-muted small mt-1">{{ $weight->source }}</div>
                            <input type="hidden" name="survey_phase" value="{{ $phase }}" form="{{ $formId }}">
                        </td>
                        <td>
                            <input name="question_key" class="form-control form-control-sm mb-2" value="{{ $weight->question_key }}" form="{{ $formId }}">
                            <textarea name="indicator" class="form-control form-control-sm" rows="2" form="{{ $formId }}">{{ $weight->indicator }}</textarea>
                        </td>
                        <td><input name="option_value" class="form-control form-control-sm fw-semibold" value="{{ $weight->option_value }}" form="{{ $formId }}"></td>
                        <td><input name="weight" class="form-control form-control-sm" value="{{ $weight->weight }}" form="{{ $formId }}"></td>
                        <td><input name="option_score" class="form-control form-control-sm fw-bold" value="{{ $weight->option_score }}" form="{{ $formId }}"></td>
                        <td><input name="category" class="form-control form-control-sm" value="{{ $weight->category }}" form="{{ $formId }}"></td>
                        <td>
                            <form id="{{ $formId }}" method="POST" action="{{ route('heks.scoring-weights.update', $weight) }}">
                                @csrf
                                @method('PUT')
                                <button class="btn btn-sm btn-light-primary">حفظ</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">لا توجد إعدادات سكور لهذه المرحلة.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
            <div class="heks-pagination">
                {{ $weights->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
