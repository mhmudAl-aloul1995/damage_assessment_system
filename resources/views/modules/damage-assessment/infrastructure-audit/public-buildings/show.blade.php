@extends('layouts.app')

@section('title', $title)
@section('pageName', $title)

@section('content')
    <style>
        .inf-audit-edit-card {
            background: #fff8dd;
            border: 1px solid #ffe7a3;
            border-radius: .85rem;
            padding: 1rem;
            text-align: center;
            max-width: 380px;
            margin: auto;
            line-height: 1.8;
        }

        .inf-audit-label {
            color: #7e8299;
            font-size: .82rem;
            font-weight: 800;
        }

        .inf-audit-original-value {
            color: #5e6278;
            font-weight: 800;
        }

        .inf-audit-new-value {
            color: #181c32;
            font-weight: 900;
        }
    </style>

    <div class="card card-flush mb-6">
        <div class="card-header pt-6">
            <div class="card-title">
                <div>
                    <h2 class="fw-bold mb-1">{{ $survey->building_name ?? $survey->str_name ?? $survey->objectid }}</h2>
                    <div class="text-muted">ObjectID: {{ $survey->objectid }} | GlobalID: {{ $survey->globalid }}</div>
                </div>
            </div>
            <div class="card-toolbar d-flex gap-2 flex-wrap">
                <a href="{{ $backRoute }}" class="btn btn-light">رجوع</a>
                @role('Database Officer|Team Leader -INF')
                    <select id="status_assignee" class="form-select form-select-solid w-250px" data-placeholder="اختر المدقق">
                        <option value=""></option>
                        @foreach ($engineers as $engineer)
                            <option value="{{ $engineer->id }}" @selected(($assignment?->user_id ?? $survey->infAuditStatus?->assigned_to ?? null) === $engineer->id)>{{ $engineer->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-light-info status-btn" data-status="assigned" @disabled(($currentStatusName ?? null) === 'assigned')>إسناد</button>
                    <button class="btn btn-success status-btn" data-status="final_approval" @disabled(in_array(($currentStatusName ?? null), ['final_approval', 'accepted_final', 'final'], true))>اعتماد نهائي</button>
                @endrole
                @role('Database Officer|Team Leader -INF|Inf - QC/QA Engineer')
                    <button class="btn btn-light-success status-btn" data-status="accepted" @disabled(($currentStatusName ?? null) === 'accepted')>مقبول</button>
                    <button class="btn btn-light-danger status-btn" data-status="rejected" @disabled(($currentStatusName ?? null) === 'rejected')>مرفوض</button>
                    <button class="btn btn-light-warning status-btn" data-status="need_review" @disabled(($currentStatusName ?? null) === 'need_review')>بحاجة لمراجعة</button>
                @endrole
            </div>
        </div>
    </div>

    <div class="row g-6 mb-6">
        <div class="col-12">
            <div class="card card-flush shadow-sm h-100">
                <div class="card-header pt-5">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">معلومات الإسناد</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <span class="text-muted fw-bold">المدقق:</span>
                            <span class="fw-bold" id="assignment_user_name">{{ $assignment?->user?->name ?? $survey->infAuditStatus?->assignee?->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-muted fw-bold">المسند بواسطة:</span>
                            <span class="fw-bold" id="assignment_manager_name">{{ $assignment?->manager?->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-muted fw-bold">وقت الإسناد:</span>
                            <span class="fw-bold" id="assignment_updated_at">{{ $assignment?->updated_at?->format('Y-m-d H:i') ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-6 mb-6">
        @isset($roadLength)
            <div class="col-md-4">
                <div class="card card-flush shadow-sm h-100">
                    <div class="card-header pt-5">
                        <div class="card-title">
                            <h3 class="fw-bold mb-0">طول الشارع</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <span class="fs-2 fw-bold">{{ $roadLength }}</span>
                        <span class="text-muted fw-semibold">متر</span>
                    </div>
                </div>
            </div>
        @endisset

        <div class="col-md-{{ isset($roadLength) ? '8' : '12' }}">
            <div class="card card-flush shadow-sm h-100">
                <div class="card-header pt-5">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">مرفقات ArcGIS</h3>
                    </div>
                </div>
                <div class="card-body">
                    @forelse ($arcgisAttachments ?? [] as $attachment)
                        @php($isImage = str_starts_with((string) $attachment['content_type'], 'image/'))
                        <a href="{{ $attachment['url'] }}" target="_blank" class="d-inline-flex align-items-center gap-2 border rounded p-2 me-2 mb-2">
                            @if ($isImage)
                                <img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] }}" class="rounded" style="width: 76px; height: 58px; object-fit: cover;">
                            @else
                                <span class="badge badge-light-primary">ملف</span>
                            @endif
                            <span class="fw-semibold">{{ $attachment['name'] }}</span>
                        </a>
                    @empty
                        <span class="text-muted">لا توجد مرفقات.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-5">
            <div class="card-title">
                <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                    <li class="nav-item">
                        <a class="nav-link text-active-primary active" data-bs-toggle="tab" href="#inf_audit_main_tab">
                            {{ $mainSectionTitle ?? 'بيانات التدقيق' }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-active-primary" data-bs-toggle="tab" href="#inf_audit_children_tab">
                            {{ $childSectionTitle ?? 'البيانات التابعة' }}
                            <span class="badge badge-light-primary ms-2">{{ count($childGroups ?? []) }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-6" id="survey_field_filter">
                <button type="button" class="btn btn-sm btn-primary survey-filter-btn" data-filter="all">الكل</button>
                <button type="button" class="btn btn-sm btn-light-primary survey-filter-btn" data-filter="edited">المعدل</button>
                <button type="button" class="btn btn-sm btn-light-primary survey-filter-btn" data-filter="answered">له جواب</button>
                <button type="button" class="btn btn-sm btn-light-primary survey-filter-btn" data-filter="unanswered">ليس له جواب</button>
            </div>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="inf_audit_main_tab" role="tabpanel">
                    @include('modules.damage-assessment.infrastructure-audit.public-buildings._audit_sections', [
                        'sectionTitle' => $mainSectionTitle ?? 'بيانات التدقيق',
                        'sections' => $sections,
                    ])
                </div>

                <div class="tab-pane fade" id="inf_audit_children_tab" role="tabpanel">
                    @isset($childStoreRoute)
                        @role('Database Officer|Team Leader -INF|Inf - QC/QA Engineer')
                            <div class="d-flex justify-content-end mb-5">
                                <button type="button" class="btn btn-light-primary" id="addChildAuditRecord">
                                    {{ $childAddLabel ?? 'إضافة سجل تابع' }}
                                </button>
                            </div>
                        @endrole
                    @endisset

                    @forelse ($childGroups as $group)
                        <div class="card card-flush border shadow-sm mb-6">
                            <div class="card-header pt-6">
                                <div class="card-title">
                                    <h3 class="fw-bold mb-0">{{ $group['title'] }}</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                @foreach ($group['sections'] as $section)
                                    @include('modules.damage-assessment.infrastructure-audit.public-buildings._field_table', ['section' => $section])
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-light">لا توجد بيانات تابعة للتدقيق.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fieldEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="fw-bold" id="fieldEditTitle">تعديل الحقل</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_table_type">
                    <input type="hidden" id="edit_auditable_id">
                    <input type="hidden" id="edit_field_name">
                    <label class="form-label fw-bold">القيمة الجديدة</label>
                    <select id="edit_field_select" class="form-select form-select-solid d-none" data-control="select2"></select>
                    <textarea id="edit_field_value" class="form-control form-control-solid" rows="5"></textarea>
                    <label class="form-label fw-bold mt-4">ملاحظات</label>
                    <textarea id="edit_notes" class="form-control form-control-solid" rows="2"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" id="saveFieldEdit">حفظ</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(function () {
            $('#status_assignee').select2({ width: '250px', allowClear: true });
            let currentEditButton = null;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function parseOptions(btn) {
                try {
                    return JSON.parse(btn.attr('data-options') || '[]');
                } catch (e) {
                    return [];
                }
            }

            function resetSelectInput() {
                const select = $('#edit_field_select');

                if (select.hasClass('select2-hidden-accessible')) {
                    select.select2('destroy');
                }

                select.empty().prop('multiple', false).addClass('d-none');
                $('#edit_field_value').removeClass('d-none');
            }

            function selectedValues(rawValue, fieldType) {
                if (!rawValue) {
                    return fieldType === 'select_multiple' ? [] : '';
                }

                return fieldType === 'select_multiple'
                    ? String(rawValue).split(/[,\s]+/).filter(Boolean)
                    : String(rawValue);
            }

            function applySurveyFilter(filter) {
                $('[data-field-row]').each(function () {
                    const row = $(this);
                    const hasAnswer = row.attr('data-has-answer') === '1';
                    const isEdited = row.attr('data-is-edited') === '1';
                    const visible = filter === 'all'
                        || (filter === 'edited' && isEdited)
                        || (filter === 'answered' && hasAnswer)
                        || (filter === 'unanswered' && !hasAnswer);

                    row.toggle(visible);
                });
            }

            $('.survey-filter-btn').on('click', function () {
                $('.survey-filter-btn').removeClass('btn-primary').addClass('btn-light-primary');
                $(this).removeClass('btn-light-primary').addClass('btn-primary');
                applySurveyFilter($(this).data('filter'));
            });

            function renderHistoryCard(response, historyId) {
                const history = response.history || [];

                if (!history.length) {
                    return '<span>' + escapeHtml(response.display_value || 'لا يوجد جواب') + '</span>';
                }

                const latest = history[0];
                const items = history.map(function (item) {
                    const notes = item.notes
                        ? '<div><span class="inf-audit-label">ملاحظات</span>: ' + escapeHtml(item.notes) + '</div>'
                        : '';

                    return `
                        <div class="border rounded bg-light-info p-2 mb-2 text-start">
                            <div><span class="inf-audit-label">القيمة</span>: <span class="fw-semibold">${escapeHtml(item.field_value || '-')}</span></div>
                            <div><span class="inf-audit-label">الأصل</span>: ${escapeHtml(item.old_value || '-')}</div>
                            <div><span class="inf-audit-label">المستخدم</span>: ${escapeHtml(item.user_name || '-')}</div>
                            <div><span class="inf-audit-label">الوقت</span>: ${escapeHtml(item.created_at || '-')}</div>
                            ${notes}
                        </div>
                    `;
                }).join('');

                return `
                    <div class="inf-audit-edit-card">
                        <div class="inf-audit-label">الأصل</div>
                        <div class="inf-audit-original-value">${escapeHtml(latest.old_value || 'لا يوجد جواب')}</div>

                        <div class="inf-audit-label text-warning mt-3">آخر تعديل</div>
                        <div class="inf-audit-new-value">${escapeHtml(latest.field_value || 'لا يوجد جواب')}</div>

                        <div class="inf-audit-label text-primary mt-3">اسم المعدّل</div>
                        <div class="fw-bold">${escapeHtml(latest.user_name || '-')}</div>

                        <div class="inf-audit-label text-primary mt-3">وقت التعديل</div>
                        <div class="fw-bold">${escapeHtml(latest.created_at || '-')}</div>

                        <button type="button"
                            class="btn btn-sm btn-light-primary mt-4"
                            data-bs-toggle="collapse"
                            data-bs-target="#${escapeHtml(historyId)}">
                            عرض سجل التعديلات (${history.length})
                        </button>

                        <div class="collapse mt-3" id="${escapeHtml(historyId)}">
                            ${items}
                        </div>
                    </div>
                `;
            }

            $('.edit-field-btn').on('click', function () {
                const btn = $(this);
                const emptyAnswerText = 'لا يوجد جواب';
                const fieldType = btn.data('field-type');
                const options = parseOptions(btn);
                const rawValue = btn.attr('data-raw-value') || '';

                currentEditButton = btn;

                $('#fieldEditTitle').text(btn.data('label'));
                $('#edit_table_type').val(btn.data('table-type'));
                $('#edit_auditable_id').val(btn.data('record-id'));
                $('#edit_field_name').val(btn.data('field-name'));
                $('#edit_notes').val('');
                resetSelectInput();

                if ((fieldType === 'select_one' || fieldType === 'select_multiple') && options.length) {
                    const select = $('#edit_field_select');
                    const currentValue = selectedValues(rawValue, fieldType);

                    $('#edit_field_value').addClass('d-none').val('');
                    select.removeClass('d-none').prop('multiple', fieldType === 'select_multiple');

                    if (fieldType === 'select_one') {
                        select.append(new Option('', '', false, false));
                    }

                    options.forEach(function (option) {
                        const selected = Array.isArray(currentValue)
                            ? currentValue.includes(String(option.value))
                            : String(option.value) === String(currentValue);

                        select.append(new Option(option.label, option.value, selected, selected));
                    });

                    select.select2({
                        width: '100%',
                        allowClear: true,
                        dropdownParent: $('#fieldEditModal')
                    });
                } else {
                    $('#edit_field_value').val(btn.data('value') === emptyAnswerText ? '' : btn.data('value'));
                }

                bootstrap.Modal.getOrCreateInstance(document.getElementById('fieldEditModal')).show();
            });

            $('#saveFieldEdit').on('click', function () {
                const select = $('#edit_field_select');
                const fieldValue = select.hasClass('d-none')
                    ? $('#edit_field_value').val()
                    : (Array.isArray(select.val()) ? select.val().join(',') : select.val());

                $.post(@json($fieldRoute), {
                    _token: @json(csrf_token()),
                    table_type: $('#edit_table_type').val(),
                    auditable_id: $('#edit_auditable_id').val(),
                    field_name: $('#edit_field_name').val(),
                    field_value: fieldValue,
                    notes: $('#edit_notes').val()
                }).done(function (response) {
                    toastr.success(response.message || 'تم الحفظ');
                    if (currentEditButton) {
                        const row = currentEditButton.closest('tr');
                        const historyId = currentEditButton.attr('data-history-id');

                        row.find('.inf-audit-answer-cell').html(renderHistoryCard(response, historyId));
                        row.attr('data-is-edited', '1').attr('data-has-answer', response.raw_value ? '1' : '0');
                        currentEditButton
                            .attr('data-value', response.display_value || '')
                            .attr('data-raw-value', response.raw_value || '')
                            .data('value', response.display_value || '');
                    }

                    bootstrap.Modal.getOrCreateInstance(document.getElementById('fieldEditModal')).hide();
                }).fail(function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء الحفظ');
                });
            });

            $('#addChildAuditRecord').on('click', function () {
                const button = $(this);
                button.prop('disabled', true);

                $.post(@json($childStoreRoute ?? null), {
                    _token: @json(csrf_token())
                }).done(function (response) {
                    toastr.success(response.message || 'تمت الإضافة بنجاح');
                    window.location.reload();
                }).fail(function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء الإضافة');
                    button.prop('disabled', false);
                });
            });

            $('.status-btn').on('click', function () {
                const statusButton = $(this);

                $.post(@json($statusRoute), {
                    _token: @json(csrf_token()),
                    status: statusButton.data('status'),
                    assigned_to: $('#status_assignee').val()
                }).done(function (response) {
                    toastr.success(response.message || 'تم تحديث الحالة');
                    $('.status-btn').prop('disabled', false);
                    statusButton.prop('disabled', true);
                    if (response.assignment) {
                        $('#assignment_user_name').text(response.assignment.user_name || '-');
                        $('#assignment_manager_name').text(response.assignment.manager_name || '-');
                        $('#assignment_updated_at').text(response.assignment.updated_at || '-');
                    }
                }).fail(function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء تحديث الحالة');
                });
            });
        });
    </script>
@endsection
