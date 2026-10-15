<header class="card cso-decision-header mb-5" id="cso_decision_header">
    <div class="cso-decision-content p-4">
        <div class="cso-decision-identity">
            <h2 class="fs-4 fw-bold mb-2" id="cso_survey_title" title="{{ $survey->organization_name ?: $survey->building_name }}">{{ $survey->organization_name ?: $survey->building_name ?: 'استبيان '.$survey->objectid }}</h2>
            <div class="d-flex flex-wrap align-items-center gap-2 fs-8">
                <span>استبيان <b dir="ltr">#{{ $survey->objectid }}</b></span>
                <span class="{{ $statuses->firstWhere('name', $currentStatusName)?->badge_class ?? 'badge badge-light-primary' }}" id="cso_current_status" aria-live="polite">{{ $statuses->firstWhere('name', $currentStatusName)?->label ?? 'لم يبدأ التدقيق' }}</span>
                @if ($survey->field_status === 'COMPLETED')<span class="badge badge-light-success cso-field-complete">مكتمل ميدانياً</span>@endif
            </div>
        </div>
        <div class="cso-decision-actions" role="group" aria-label="قرار التدقيق للاستبيان كاملاً">
            @role('Database Officer|Team Leader -INF|CSO Officer|Inf - QC/QA Engineer')
                <button type="button" class="btn btn-sm btn-primary status-btn" data-status="accepted" @disabled($currentStatusName === 'accepted')><span class="cso-desktop-label">قبول التدقيق</span><span class="cso-mobile-label">قبول</span></button>
                <button type="button" class="btn btn-sm btn-light-warning status-btn" data-status="need_review" @disabled($currentStatusName === 'need_review')><span class="cso-desktop-label">إعادة للمراجعة</span><span class="cso-mobile-label">مراجعة</span></button>
                <button type="button" class="btn btn-sm btn-light-danger status-btn" data-status="rejected" @disabled($currentStatusName === 'rejected')>رفض</button>
            @endrole
            @role('Database Officer|Team Leader -INF|CSO Officer')
                <button type="button" class="btn btn-sm btn-light-success status-btn" data-status="final_approval" @disabled(in_array($currentStatusName, ['final_approval', 'accepted_final', 'final'], true))>اعتماد نهائي</button>
            @endrole
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-light" id="cso_assignment_toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="الإسناد وتفاصيل السجل" title="الإسناد وتفاصيل السجل"><span class="cso-desktop-label">الإسناد والتفاصيل</span><span class="cso-mobile-label">التفاصيل</span></button>
                <div class="dropdown-menu p-5 cso-assignment-menu" id="cso_assignment_menu" aria-labelledby="cso_assignment_toggle">
                    <h3 class="fs-6 fw-bold mb-4">الإسناد وتفاصيل الاستبيان</h3>
                    <div class="d-flex flex-column gap-3 fs-7 mb-4">
                        <div><span class="text-muted">المدقق:</span> <b id="assignment_user_name">{{ $assignment?->user?->name ?? $survey->infAuditStatus?->assignee?->name ?? 'غير مسند' }}</b></div>
                        <div><span class="text-muted">أسند بواسطة:</span> <span id="assignment_manager_name">{{ $assignment?->manager?->name ?? '—' }}</span></div>
                        <div><span class="text-muted">وقت الإسناد:</span> <span id="assignment_updated_at" dir="ltr">{{ $assignment?->updated_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
                        <div>{{ collect([$survey->municipalitie, $survey->neighborhood])->filter()->implode(' · ') }}</div>
                        <details class="cso-record-identifiers"><summary class="text-primary">معرّف السجل</summary><span dir="ltr">{{ $survey->globalid }}</span></details>
                    </div>
                    @role('Database Officer|Team Leader -INF|CSO Officer')
                        <div class="cso-assignment-controls">
                            <label for="status_assignee" class="form-label fw-semibold fs-7">إسناد للمدقق</label>
                            <select id="status_assignee" class="form-select form-select-solid" data-placeholder="اختر المدقق">
                                <option value=""></option>
                                @foreach ($engineers as $engineer)
                                    <option value="{{ $engineer->id }}" @selected((int) ($assignment?->user_id ?? $survey->infAuditStatus?->assigned_to) === $engineer->id)>{{ $engineer->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-light-primary status-btn mt-3" data-status="assigned" @disabled($currentStatusName === 'assigned')>إسناد</button>
                        </div>
                    @endrole
                </div>
            </div>
        </div>
        <a href="{{ $backRoute }}" class="btn btn-sm btn-light cso-back-link">رجوع<span class="cso-desktop-label"> للقائمة</span></a>
    </div>
</header>
