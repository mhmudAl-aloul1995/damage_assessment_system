@extends('layouts.app')

@section('title', $title)
@section('pageName', $title)

@section('content')
    @include('damage-assessment::infrastructure-audit.cso._workspace_styles')

    <div class="cso-detail-workspace" id="cso_audit_workspace">
        <header class="card cso-record-header mb-5">
            <div class="card-body p-5">
                <div class="d-flex justify-content-between align-items-start gap-4">
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-muted fs-7 fw-semibold mb-2">تدقيق منظمات المجتمع المدني</div>
                        <h2 class="fs-2 fw-bold mb-3" id="cso_survey_title">{{ $survey->organization_name ?: $survey->building_name ?: 'استبيان '.$survey->objectid }}</h2>
                        <div class="d-flex flex-wrap align-items-center gap-3 fs-7">
                            <span class="cso-record-number">استبيان <b dir="ltr">#{{ $survey->objectid }}</b></span>
                            <span class="text-muted">{{ collect([$survey->municipalitie, $survey->neighborhood])->filter()->implode(' · ') }}</span>
                            @if ($survey->field_status === 'COMPLETED')
                                <span class="badge badge-light-success">مكتمل ميدانياً</span>
                            @endif
                            <span class="{{ $statuses->firstWhere('name', $currentStatusName)?->badge_class ?? 'badge badge-light-primary' }}" id="cso_current_status" aria-live="polite">{{ $statuses->firstWhere('name', $currentStatusName)?->label ?? 'لم يبدأ التدقيق' }}</span>
                        </div>
                    </div>
                    <a href="{{ $backRoute }}" class="btn btn-sm btn-light flex-shrink-0">رجوع للقائمة</a>
                </div>
                <div class="cso-assignment-summary mt-4 pt-4 fs-7">
                    <span><span class="text-muted">المدقق:</span> <b id="assignment_user_name">{{ $assignment?->user?->name ?? $survey->infAuditStatus?->assignee?->name ?? 'غير مسند' }}</b></span>
                    <span><span class="text-muted">أسند بواسطة:</span> <span id="assignment_manager_name">{{ $assignment?->manager?->name ?? '—' }}</span></span>
                    <span><span class="text-muted">وقت الإسناد:</span> <span id="assignment_updated_at" dir="ltr">{{ $assignment?->updated_at?->format('Y-m-d H:i') ?? '—' }}</span></span>
                    <details class="cso-record-identifiers">
                        <summary class="text-primary">معرّف السجل</summary>
                        <span dir="ltr">{{ $survey->globalid }}</span>
                    </details>
                </div>
            </div>
        </header>

        <div class="card mb-5">
            <div class="card-body p-0">
                <nav class="cso-workspace-tabs px-5" aria-label="أقسام تدقيق الاستبيان">
                    <div class="nav nav-line-tabs nav-line-tabs-2x border-0" role="tablist">
                        @foreach (['survey' => ['بيانات الاستبيان', null], 'organizations' => ['المنظمات', count($organizationGroups)], 'units' => ['الوحدات', count($unitGroups)]] as $key => [$label, $count])
                            <button type="button" class="nav-link fw-bold {{ $loop->first ? 'active' : '' }}" id="cso_{{ $key }}_tab" data-bs-toggle="tab" data-bs-target="#cso_{{ $key }}_pane" role="tab" aria-controls="cso_{{ $key }}_pane" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                {{ $label }} @if ($count !== null)<span class="badge badge-light ms-2">{{ $count }}</span>@endif
                            </button>
                        @endforeach
                    </div>
                </nav>

                <div class="cso-field-toolbar p-5">
                    <div class="cso-field-search">
                        <label class="visually-hidden" for="cso_field_search">بحث في أسئلة السجل المفتوح</label>
                        <input type="search" id="cso_field_search" class="form-control form-control-solid" placeholder="بحث في أسئلة السجل المفتوح…">
                    </div>
                    <div class="d-flex flex-wrap gap-2" role="group" aria-label="تصفية حقول السجل المفتوح">
                        @foreach (['all' => 'الكل', 'edited' => 'المعدّلة', 'unanswered' => 'بدون إجابة'] as $filter => $label)
                            <button type="button" class="btn btn-sm {{ $loop->first ? 'btn-primary' : 'btn-light' }} cso-field-filter" data-filter="{{ $filter }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }} <span class="ms-1" data-filter-count="{{ $filter }}">0</span></button>
                        @endforeach
                    </div>
                    <span class="text-muted fs-7" id="cso_visible_fields" aria-live="polite"></span>
                </div>

                <div class="tab-content">
                    <div class="tab-pane active" id="cso_survey_pane" role="tabpanel" aria-labelledby="cso_survey_tab" tabindex="0">
                        <div class="cso-record-content p-5" data-record-content>
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-5">
                                <div><h3 class="fw-bold mb-2">بيانات الاستبيان</h3><p class="text-muted fs-7 mb-0">القيمة الحالية لكل سؤال. يمكنك فتح سجل التعديل بجانب الحقول المعدّلة.</p></div>
                                <span class="badge badge-light">القيمة الحالية</span>
                            </div>
                            @include('damage-assessment::infrastructure-audit.cso._sections', ['sections' => $sections])
                        </div>
                    </div>
                    @include('damage-assessment::infrastructure-audit.cso._records', ['kind' => 'organizations', 'groups' => $organizationGroups, 'singular' => 'منظمة', 'plural' => 'المنظمات'])
                    @include('damage-assessment::infrastructure-audit.cso._records', ['kind' => 'units', 'groups' => $unitGroups, 'singular' => 'وحدة', 'plural' => 'الوحدات'])
                </div>
            </div>
        </div>

        <details class="card mb-5 cso-attachments">
            <summary class="p-5 fw-bold">مرفقات الاستبيان <span class="badge badge-light ms-2">{{ count($arcgisAttachments ?? []) }}</span></summary>
            <div class="px-5 pb-5 d-flex flex-wrap gap-3">
                @forelse ($arcgisAttachments ?? [] as $attachment)
                    <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener" class="cso-attachment border rounded p-3 d-flex align-items-center gap-3">
                        @if (str_starts_with((string) $attachment['content_type'], 'image/'))
                            <img src="{{ $attachment['url'] }}" alt="" loading="lazy" class="rounded">
                        @else
                            <span class="badge badge-light-primary">ملف</span>
                        @endif
                        <span>{{ $attachment['name'] }}</span>
                    </a>
                @empty
                    <span class="text-muted fs-7">لا توجد مرفقات للاستبيان.</span>
                @endforelse
            </div>
        </details>

        @role('Database Officer|Team Leader -INF|CSO Officer|Inf - QC/QA Engineer')
            <div class="card cso-decision-bar">
                <div class="card-body p-5">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
                        <div><h3 class="fs-5 fw-bold mb-1">قرار التدقيق</h3><span class="text-muted fs-7">الإجراء يطبق على الاستبيان كاملاً.</span></div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-primary status-btn" data-status="accepted" @disabled($currentStatusName === 'accepted')>قبول التدقيق</button>
                            <button type="button" class="btn btn-sm btn-light-warning status-btn" data-status="need_review" @disabled($currentStatusName === 'need_review')>إعادة للمراجعة</button>
                            <button type="button" class="btn btn-sm btn-light-danger status-btn" data-status="rejected" @disabled($currentStatusName === 'rejected')>رفض</button>
                        </div>
                    </div>
                    @role('Database Officer|Team Leader -INF|CSO Officer')
                        <div class="cso-assignment-controls mt-4 pt-4">
                            <label for="status_assignee" class="fw-semibold fs-7 mb-0">إسناد للمدقق</label>
                            <select id="status_assignee" class="form-select form-select-solid" data-placeholder="اختر المدقق">
                                <option value=""></option>
                                @foreach ($engineers as $engineer)
                                    <option value="{{ $engineer->id }}" @selected((int) ($assignment?->user_id ?? $survey->infAuditStatus?->assigned_to) === $engineer->id)>{{ $engineer->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-light-primary status-btn" data-status="assigned" @disabled($currentStatusName === 'assigned')>إسناد</button>
                            <button type="button" class="btn btn-sm btn-light-success status-btn ms-auto" data-status="final_approval" @disabled(in_array($currentStatusName, ['final_approval', 'accepted_final', 'final'], true))>اعتماد نهائي</button>
                        </div>
                    @endrole
                </div>
            </div>
        @endrole
    </div>

    @if ($canEditFields)
        <div class="modal fade" id="fieldEditModal" tabindex="-1" aria-labelledby="fieldEditTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content" id="cso_field_edit_form">
                    <div class="modal-header">
                        <div><div class="text-muted fs-7 mb-1" id="fieldEditContext"></div><h3 class="fw-bold mb-0" id="fieldEditTitle">تعديل الحقل</h3></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="rounded bg-light p-4 mb-5"><div class="text-muted fs-7 mb-2">القيمة الحالية</div><div class="fw-semibold cso-wrap-value" id="edit_current_value"></div></div>
                        <label class="form-label fw-bold" for="edit_field_value">القيمة الجديدة</label>
                        <select id="edit_field_select" class="form-select form-select-solid d-none" aria-label="القيمة الجديدة"></select>
                        <textarea id="edit_field_value" class="form-control form-control-solid" rows="4"></textarea>
                        <label class="form-label fw-bold mt-4" for="edit_notes">ملاحظة التعديل <span class="text-muted fw-normal fs-7">(اختياري)</span></label>
                        <textarea id="edit_notes" class="form-control form-control-solid" rows="2"></textarea>
                        <div class="alert alert-danger mt-4 mb-0 d-none" id="cso_edit_error" role="alert"></div>
                        <div class="text-muted fs-7 mt-4">يتم حفظ القيمة مباشرة في ArcGIS وتسجيل التعديل.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="cancelFieldEdit">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="saveFieldEdit">حفظ التعديل</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('script')
    @include('damage-assessment::infrastructure-audit.cso._workspace_script')
@endsection
