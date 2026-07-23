@extends('layouts.app')

@section('title', 'إعدادات السكور')
@section('pageName', 'إعدادات السكور')

@section('content')
    @include('heks::partials.nav')

    <style>
        .heks-score-hero {
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 48%, #f7fff9 100%);
            border: 1px solid #edf1f7;
        }

        .heks-score-table thead th {
            background: #f9fafc;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .heks-score-table textarea {
            min-height: 58px;
            resize: vertical;
        }

        .heks-score-metric {
            border: 1px solid #eef2f7;
            transition: box-shadow .15s ease, transform .15s ease;
        }

        .heks-score-metric:hover {
            box-shadow: 0 .5rem 1.5rem rgba(20, 20, 43, .06);
            transform: translateY(-1px);
        }
    </style>

    <div class="card card-flush heks-score-hero mb-6">
        <div class="card-body">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-6">
                <div class="d-flex align-items-start gap-4">
                    <div class="symbol symbol-55px">
                        <div class="symbol-label bg-light-primary">
                            <i class="ki-duotone ki-setting-2 fs-2x text-primary">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h2 class="fw-bold text-gray-900 mb-0">إعدادات السكور</h2>
                            <span class="badge badge-light-primary">{{ $phaseOptions[$phase] ?? $phase }}</span>
                        </div>
                        <div class="text-muted fs-6">
                            هذه الصفحة تضبط قواعد السكور للمرحلة المختارة، ويتم تطبيقها عند مزامنة بيانات Kobo أو إعادة مزامنتها.
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 justify-content-xl-end">
                    <a class="btn btn-sm btn-light" href="{{ route('heks.scores', ['phase' => $phase]) }}">
                        <i class="ki-duotone ki-arrows-circle fs-3"><span class="path1"></span><span class="path2"></span></i>
                        تصفية
                    </a>
                    @if ($phase === 'phase_2')
                        <button class="btn btn-sm btn-primary" form="copyPhaseTwoWeights">
                            <i class="ki-duotone ki-copy fs-3"><span class="path1"></span><span class="path2"></span></i>
                            نسخ أوزان الأولى
                        </button>
                    @endif
                </div>
            </div>

            <div class="separator my-6"></div>

            <form method="GET" action="{{ route('heks.scores') }}" class="row g-4 align-items-end">
                <div class="col-xl-4 col-md-6">
                    <label class="form-label fw-semibold text-gray-700">مرحلة الاستبيان</label>
                    <select name="phase" class="form-select form-select-solid" data-control="select2" data-hide-search="true" onchange="this.form.submit()">
                        @foreach ($phaseOptions as $value => $label)
                            <option value="{{ $value }}" @selected($phase === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-4 col-md-6">
                    <label class="form-label fw-semibold text-gray-700">نوع السكور</label>
                    <select name="component" class="form-select form-select-solid" data-control="select2" data-hide-search="true" onchange="this.form.submit()">
                        @foreach ($componentOptions as $value => $label)
                            <option value="{{ $value }}" @selected($component === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-4 col-md-12">
                    <label class="form-label fw-semibold text-gray-700">المصدر</label>
                    <select name="source" class="form-select form-select-solid" data-control="select2" onchange="this.form.submit()">
                        <option value="">كل المصادر</option>
                        @foreach ($sourceOptions as $sourceOption)
                            <option value="{{ $sourceOption }}" @selected($source === $sourceOption)>{{ $sourceOption }}</option>
                        @endforeach
                    </select>
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
            ['label' => 'إجمالي القواعد', 'value' => number_format((float) $weightSummary['total']), 'icon' => 'ki-category', 'tone' => 'primary'],
            ['label' => 'مصادر الأوزان', 'value' => number_format((float) $weightSummary['sources']), 'icon' => 'ki-data', 'tone' => 'info'],
            ['label' => 'الأسئلة', 'value' => number_format((float) $weightSummary['questions']), 'icon' => 'ki-questionnaire-tablet', 'tone' => 'warning'],
            ['label' => 'خيارات بنقاط', 'value' => number_format((float) $weightSummary['option_scores']), 'icon' => 'ki-chart-simple', 'tone' => 'success'],
        ] as $metric)
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush h-100 heks-score-metric">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted fw-semibold mb-1">{{ $metric['label'] }}</div>
                            <div class="fs-2hx fw-bold text-gray-900">{{ $metric['value'] }}</div>
                        </div>
                        <div class="symbol symbol-45px">
                            <div class="symbol-label bg-light-{{ $metric['tone'] }}">
                                <i class="ki-duotone {{ $metric['icon'] }} fs-2 text-{{ $metric['tone'] }}">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-flush mb-6">
        <div class="card-header align-items-center">
            <div class="card-title flex-column">
                <h3 class="fw-bold mb-1">مصادر إعدادات السكور</h3>
                <div class="text-muted fs-7">اختر المصدر أو نوع السكور من الأعلى لتقليل الجدول والتركيز على القواعد المطلوبة.</div>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="d-flex flex-wrap gap-3">
                @forelse ($sources as $sourceName => $count)
                    @php($sourceTone = $sourceName === 'S-V' ? 'success' : 'primary')
                    <a class="badge badge-light-{{ $sourceTone }} fs-7 px-4 py-3" href="{{ route('heks.scores', ['phase' => $phase, 'component' => $component, 'source' => $sourceName]) }}">
                        {{ $sourceName }} · {{ number_format((float) $count) }}
                    </a>
                @empty
                    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-5 w-100">
                        <i class="ki-duotone ki-information-5 fs-2x text-warning me-4">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                        <div class="text-gray-700">لا توجد إعدادات محفوظة لهذه المرحلة.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header align-items-center">
            <div class="card-title flex-column">
                <h3 class="fw-bold mb-1">قواعد وأوزان السكور</h3>
                <div class="text-muted fs-7">عدّل الوزن أو نقاط الخيار ثم احفظ الصف المطلوب فقط.</div>
            </div>
            <div class="card-toolbar">
                <span class="badge badge-light">{{ $weights->firstItem() ?? 0 }} - {{ $weights->lastItem() ?? 0 }} من {{ number_format($weights->total()) }}</span>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive" style="max-height: 70vh;">
                <table class="table table-row-dashed table-hover align-middle gy-4 heks-score-table">
                    <thead>
                    <tr class="fw-bold text-muted text-uppercase fs-8">
                        <th class="min-w-130px">النوع</th>
                        <th class="min-w-280px">السؤال</th>
                        <th class="min-w-220px">الخيار / المعيار</th>
                        <th class="min-w-120px">الوزن</th>
                        <th class="min-w-140px">نقاط الخيار</th>
                        <th class="min-w-190px">الفئة</th>
                        <th class="min-w-95px text-end"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($weights as $weight)
                        @php($formId = 'score-weight-'.$weight->id)
                        @php($isSocial = $weight->source === 'S-V')
                        <tr>
                            <td>
                                <span class="badge badge-light-{{ $isSocial ? 'success' : 'primary' }} mb-2">{{ $isSocial ? 'اجتماعي' : 'فني' }}</span>
                                <div class="text-muted fs-8">{{ $weight->source }}</div>
                                <input type="hidden" name="survey_phase" value="{{ $phase }}" form="{{ $formId }}">
                            </td>
                            <td>
                                <input name="question_key" class="form-control form-control-sm form-control-solid mb-2 text-gray-800" value="{{ $weight->question_key }}" form="{{ $formId }}">
                                <textarea name="indicator" class="form-control form-control-sm" rows="2" form="{{ $formId }}">{{ $weight->indicator }}</textarea>
                            </td>
                            <td>
                                <input name="option_value" class="form-control form-control-sm form-control-solid fw-semibold" value="{{ $weight->option_value }}" form="{{ $formId }}">
                            </td>
                            <td>
                                <input name="weight" class="form-control form-control-sm text-center" value="{{ $weight->weight }}" form="{{ $formId }}">
                            </td>
                            <td>
                                <input name="option_score" class="form-control form-control-sm text-center fw-bold text-gray-900" value="{{ $weight->option_score }}" form="{{ $formId }}">
                            </td>
                            <td>
                                <input name="category" class="form-control form-control-sm form-control-solid" value="{{ $weight->category }}" form="{{ $formId }}">
                            </td>
                            <td class="text-end">
                                <form id="{{ $formId }}" method="POST" action="{{ route('heks.scoring-weights.update', $weight) }}">
                                    @csrf
                                    @method('PUT')
                                    <button class="btn btn-sm btn-light-primary">
                                        <i class="ki-duotone ki-check fs-3"><span class="path1"></span><span class="path2"></span></i>
                                        حفظ
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="text-center py-10">
                                    <i class="ki-duotone ki-information-5 fs-3x text-muted mb-3">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    </i>
                                    <div class="fw-semibold text-gray-700">لا توجد إعدادات سكور لهذه المرحلة.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="heks-pagination">
                {{ $weights->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
