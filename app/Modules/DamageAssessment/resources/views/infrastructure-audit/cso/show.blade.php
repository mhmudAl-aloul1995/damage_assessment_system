@extends('layouts.app')

@section('title', $title)
@section('pageName', $title)

@section('content')
    @include('damage-assessment::infrastructure-audit.cso._workspace_styles')
    @php
        $imageAttachments = collect($arcgisAttachments ?? [])->filter(fn (array $attachment): bool => str_starts_with(strtolower((string) $attachment['content_type']), 'image/'))->values()->all();
        $fileAttachments = collect($arcgisAttachments ?? [])->reject(fn (array $attachment): bool => str_starts_with(strtolower((string) $attachment['content_type']), 'image/'))->values()->all();
    @endphp

    <div class="cso-detail-workspace" id="cso_audit_workspace">
        @include('damage-assessment::infrastructure-audit.cso._decision_header')

        <div class="cso-audit-layout">
            <main class="card cso-audit-data" aria-label="بيانات التدقيق">
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
                            <div class="cso-record-content p-5" data-record-content tabindex="0" aria-label="حقول الاستبيان">
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
            </main>
            @include('damage-assessment::infrastructure-audit.cso._attachment_gallery')
        </div>
    </div>

    @include('damage-assessment::infrastructure-audit.cso._attachment_viewer')

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
    @include('damage-assessment::infrastructure-audit.cso._gallery_script')
@endsection
