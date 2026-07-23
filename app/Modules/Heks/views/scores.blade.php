@extends('layouts.app')

@section('title', 'إعدادات السكور')
@section('pageName', 'إعدادات السكور')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush mb-6">
        <div class="card-body">
            <form method="GET" action="{{ route('heks.scores') }}" class="row g-4 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label">مرحلة الاستبيان</label>
                    <select name="phase" class="form-select" data-control="select2" data-hide-search="true" onchange="this.form.submit()">
                        @foreach ($phaseOptions as $value => $label)
                            <option value="{{ $value }}" @selected($phase === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-8">
                    <div class="text-muted">
                        يتم تطبيق إعدادات السكور على المستفيدين حسب مرحلة الاستبيان عند مزامنة بيانات KoBo أو إعادة مزامنتها.
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-6">
        @foreach ([
            'إجمالي قواعد السكور' => number_format((float) $weightSummary['total']),
            'مصادر الأوزان' => number_format((float) $weightSummary['sources']),
            'الأسئلة المرتبطة' => number_format((float) $weightSummary['questions']),
            'خيارات لها نقاط' => number_format((float) $weightSummary['option_scores']),
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
                <h3 class="card-title">مصادر إعدادات السكور</h3>
                <div class="text-muted">هذه المصادر هي التي يستخدمها النظام لحساب السكور للمرحلة المختارة.</div>
            </div>
        </div>
        <div class="card-body d-flex flex-wrap gap-3">
            @forelse ($sources as $source => $count)
                <span class="badge badge-light-primary fs-7 px-4 py-3">{{ $source }}: {{ $count }}</span>
            @empty
                <span class="text-muted">لا توجد إعدادات محفوظة لهذه المرحلة.</span>
            @endforelse
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header">
            <div>
                <h3 class="card-title">قواعد وأوزان السكور</h3>
                <div class="text-muted">عدّل السؤال، الخيار، والوزن للمرحلة المختارة. نتائج المستفيدين لا تظهر هنا.</div>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                <tr class="fw-bold text-muted">
                    <th>المصدر</th>
                    <th>الفئة</th>
                    <th>المعيار</th>
                    <th>مفتاح السؤال</th>
                    <th>قيمة الخيار</th>
                    <th>الوزن</th>
                    <th>نقاط الخيار</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($weights as $weight)
                    @php($formId = 'score-weight-'.$weight->id)
                    <tr>
                        <td>
                            <span class="badge badge-light">{{ $weight->source }}</span>
                            <input type="hidden" name="survey_phase" value="{{ $phase }}" form="{{ $formId }}">
                        </td>
                        <td><input name="category" class="form-control form-control-sm" value="{{ $weight->category }}" form="{{ $formId }}"></td>
                        <td><textarea name="indicator" class="form-control form-control-sm" rows="2" form="{{ $formId }}">{{ $weight->indicator }}</textarea></td>
                        <td><input name="question_key" class="form-control form-control-sm" value="{{ $weight->question_key }}" form="{{ $formId }}"></td>
                        <td><input name="option_value" class="form-control form-control-sm" value="{{ $weight->option_value }}" form="{{ $formId }}"></td>
                        <td><input name="weight" class="form-control form-control-sm" value="{{ $weight->weight }}" form="{{ $formId }}"></td>
                        <td><input name="option_score" class="form-control form-control-sm fw-bold" value="{{ $weight->option_score }}" form="{{ $formId }}"></td>
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
                        <td colspan="8" class="text-center text-muted">لا توجد إعدادات سكور لهذه المرحلة بعد.</td>
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
