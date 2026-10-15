<script>
    $(function () {
        const workspace = $('#cso_audit_workspace');
        const statusLabels = @json($statuses->pluck('label', 'name'));
        const statusClasses = @json($statuses->pluck('badge_class', 'name'));
        const readingPositions = new Map();
        let activeFilter = 'all';
        let currentEditButton = null;
        let savingField = false;
        let changingStatus = false;

        $('#status_assignee').select2({ width: '100%', allowClear: true, dropdownParent: $('#cso_assignment_menu') });

        const decisionHeader = document.getElementById('cso_decision_header');
        const appHeader = document.getElementById('kt_app_header');
        const workspaceTabs = workspace.find('.cso-workspace-tabs')[0];
        function updateStickyMeasurements() {
            const appPosition = appHeader ? getComputedStyle(appHeader).position : '';
            const appBottom = appHeader && ['fixed', 'sticky'].includes(appPosition) ? Math.max(0, appHeader.getBoundingClientRect().bottom) : 0;
            workspace[0].style.setProperty('--cso-app-top', Math.ceil(appBottom + 8) + 'px');
            workspace[0].style.setProperty('--cso-header-height', Math.ceil(decisionHeader.getBoundingClientRect().height) + 'px');
            workspace[0].style.setProperty('--cso-tabs-height', Math.ceil(workspaceTabs.getBoundingClientRect().height) + 'px');
        }
        const stickyObserver = new ResizeObserver(updateStickyMeasurements);
        stickyObserver.observe(decisionHeader);
        stickyObserver.observe(workspaceTabs);
        if (appHeader) { stickyObserver.observe(appHeader); }
        window.addEventListener('resize', updateStickyMeasurements);
        updateStickyMeasurements();

        function hasAnswer(value) {
            return value !== null && value !== undefined && String(value).trim() !== '';
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function activeContent() {
            return workspace.find('.tab-pane.active [data-record-content]').filter(function () { return !this.hidden; }).first();
        }

        function readingKey(content) {
            return content.attr('id') || content.closest('[role="tabpanel"]').attr('id');
        }

        function readingTop() {
            return decisionHeader.getBoundingClientRect().bottom + workspaceTabs.getBoundingClientRect().height + 24;
        }

        function rememberReading(content) {
            if (content.length) { readingPositions.set(readingKey(content), Math.max(0, readingTop() - content[0].getBoundingClientRect().top)); }
        }

        function restoreReading(content) {
            if (!content.length) { return; }
            window.scrollTo(0, window.scrollY + content[0].getBoundingClientRect().top + (readingPositions.get(readingKey(content)) ?? 0) - readingTop());
        }

        function applyFieldFilter() {
            const content = activeContent();
            const query = $('#cso_field_search').val().trim().toLocaleLowerCase();
            const rows = content.find('[data-field-row]');
            const counts = { all: rows.length, edited: 0, unanswered: 0 };
            let visible = 0;

            rows.each(function () {
                const row = $(this);
                const edited = row.attr('data-is-edited') === '1';
                const unanswered = row.attr('data-has-answer') !== '1';
                counts.edited += edited ? 1 : 0;
                counts.unanswered += unanswered ? 1 : 0;
                const searchable = row.find('.cso-field-label').text() + ' ' + row.find('.cso-current-value').text();
                const matches = (activeFilter === 'all' || (activeFilter === 'edited' && edited) || (activeFilter === 'unanswered' && unanswered))
                    && searchable.toLocaleLowerCase().includes(query);
                this.hidden = !matches;
                visible += matches ? 1 : 0;
            });

            content.find('[data-field-section]').each(function () {
                this.hidden = $(this).find('[data-field-row]').filter(function () { return !this.hidden; }).length === 0;
            });
            content.find('.cso-no-fields').prop('hidden', visible > 0);
            Object.entries(counts).forEach(([filter, count]) => workspace.find('[data-filter-count="' + filter + '"]').text(count));
            $('#cso_visible_fields').text(content.length ? visible + ' من ' + rows.length + ' سؤال' : '');
        }

        workspace.on('click', '.cso-field-filter', function () {
            activeFilter = this.dataset.filter;
            workspace.find('.cso-field-filter').removeClass('btn-primary').addClass('btn-light').attr('aria-pressed', 'false');
            $(this).removeClass('btn-light').addClass('btn-primary').attr('aria-pressed', 'true');
            applyFieldFilter();
        });
        $('#cso_field_search').on('input', applyFieldFilter);
        workspace.find('[data-bs-toggle="tab"]').on('show.bs.tab', function () { rememberReading(activeContent()); });
        workspace.find('[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
            applyFieldFilter();
            const content = activeContent();
            if (readingPositions.has(readingKey(content))) { restoreReading(content); }
        });

        function selectRecord(browser, targetId) {
            const records = browser.find('[data-record-content]');
            const target = records.filter(function () { return this.id === targetId; });
            if (!target.length) { return; }
            const current = records.filter(function () { return !this.hidden; });
            if (current.attr('id') === targetId) { return; }

            rememberReading(current);
            records.prop('hidden', true);
            target.prop('hidden', false);
            browser.find('.cso-record-select').each(function () {
                const selected = this.dataset.recordTarget === targetId;
                $(this).toggleClass('active', selected).attr('aria-pressed', String(selected));
            });
            applyFieldFilter();
            restoreReading(target);
        }

        workspace.on('click', '.cso-record-select', function () {
            selectRecord($(this).closest('[data-record-browser]'), this.dataset.recordTarget);
        });
        workspace.on('click', '.cso-record-step', function () {
            const browser = $(this).closest('[data-record-browser]');
            const records = browser.find('[data-record-content]');
            const index = records.index($(this).closest('[data-record-content]')) + Number(this.dataset.step);
            const target = records.get(index);
            if (target && index >= 0) { selectRecord(browser, target.id); }
        });

        function renderHistory(history, historyId) {
            if (!history.length) { return ''; }
            const items = history.map(item => `
                <div class="cso-history-item p-3 mb-2">
                    <div class="d-flex flex-wrap gap-2 text-muted fs-8 mb-2"><b>${escapeHtml(item.user_name ?? '—')}</b><span dir="ltr">${escapeHtml(item.created_at ?? '—')}</span></div>
                    <div class="cso-wrap-value fs-7"><span class="text-muted">السابق:</span> ${escapeHtml(item.old_value ?? 'لا يوجد جواب')}</div>
                    <div class="cso-wrap-value fs-7"><span class="text-muted">الجديد:</span> ${escapeHtml(item.field_value ?? 'لا يوجد جواب')}</div>
                    ${item.notes ? '<div class="cso-wrap-value fs-7 mt-2">' + escapeHtml(item.notes) + '</div>' : ''}
                </div>`).join('');
            return `<button type="button" class="btn btn-sm btn-light-primary mt-2 cso-history-toggle" data-bs-toggle="collapse" data-bs-target="#${escapeHtml(historyId)}" aria-controls="${escapeHtml(historyId)}" aria-expanded="false">تم تعديله · السجل (${history.length})</button>
                <div class="collapse" id="${escapeHtml(historyId)}"><div class="cso-history-items mt-3">${items}</div></div>`;
        }

        function resetSelectInput() {
            const select = $('#edit_field_select');
            if (select.hasClass('select2-hidden-accessible')) { select.select2('destroy'); }
            select.empty().prop('multiple', false).addClass('d-none');
            $('#edit_field_value').removeClass('d-none');
        }

        workspace.on('click', '.edit-field-btn', function () {
            const button = $(this);
            const fieldType = button.attr('data-field-type');
            const rawValue = button.attr('data-raw-value') ?? '';
            let options = [];
            try { options = JSON.parse(button.attr('data-options') || '[]'); } catch { options = []; }
            currentEditButton = button;
            const title = button.closest('[data-record-content]').find('.cso-child-title').text() || $('#cso_survey_title').text();
            $('#fieldEditContext').text(title);
            $('#fieldEditTitle').text(button.attr('data-label'));
            $('#edit_current_value').text(button.attr('data-value'));
            $('#edit_notes').val('');
            $('#cso_edit_error').addClass('d-none').text('');
            resetSelectInput();

            if (['select_one', 'select_multiple'].includes(fieldType) && options.length) {
                const select = $('#edit_field_select');
                const multiple = fieldType === 'select_multiple';
                const values = multiple ? rawValue.split(/[,\s]+/).filter(Boolean) : [rawValue];
                $('#edit_field_value').addClass('d-none').val('');
                select.removeClass('d-none').prop('multiple', multiple);
                if (!multiple) { select.append(new Option('', '', false, false)); }
                options.forEach(option => {
                    const selected = values.includes(String(option.value));
                    select.append(new Option(option.label, option.value, selected, selected));
                });
                select.select2({ width: '100%', allowClear: true, dropdownParent: $('#fieldEditModal') });
            } else {
                $('#edit_field_value').val(rawValue);
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('fieldEditModal')).show();
        });

        $('#fieldEditModal').on('hide.bs.modal', function (event) {
            if (savingField) { event.preventDefault(); }
        });

        $('#cso_field_edit_form').on('submit', function (event) {
            event.preventDefault();
            if (savingField || !currentEditButton) { return; }
            savingField = true;
            const button = currentEditButton;
            const select = $('#edit_field_select');
            const selected = select.val();
            const value = select.hasClass('d-none') ? $('#edit_field_value').val() : (Array.isArray(selected) ? selected.join(',') : selected);
            $('#saveFieldEdit').prop('disabled', true).text('جارٍ الحفظ…');
            $('#cancelFieldEdit, #fieldEditModal .btn-close').prop('disabled', true);
            $('#cso_edit_error').addClass('d-none').text('');

            $.post(@json($fieldRoute), {
                _token: @json(csrf_token()),
                table_type: button.attr('data-table-type'),
                auditable_id: button.attr('data-record-id'),
                field_name: button.attr('data-field-name'),
                field_value: value,
                expected_value: button.attr('data-raw-value') ?? '',
                notes: $('#edit_notes').val()
            }).done(function (response) {
                const row = button.closest('[data-field-row]');
                const answered = hasAnswer(response.raw_value);
                const history = response.history ?? [];
                row.find('.cso-current-value').text(response.display_value ?? 'لا يوجد جواب').toggleClass('text-muted', !answered).toggleClass('fw-semibold', answered);
                row.find('.cso-field-history').html(renderHistory(history, button.attr('data-history-id')));
                row.attr('data-is-edited', history.length ? '1' : '0').attr('data-has-answer', answered ? '1' : '0');
                button.attr('data-value', response.display_value ?? '').attr('data-raw-value', response.raw_value ?? '');

                const record = button.closest('[data-record-content]');
                if (button.attr('data-field-name') === record.attr('data-title-field')) {
                    const recordId = record.attr('id');
                    record.find('.cso-child-title').text(response.display_value);
                    workspace.find('.cso-record-select').filter(function () { return this.dataset.recordTarget === recordId; }).find('.cso-record-select-name').text(response.display_value);
                }
                applyFieldFilter();
                savingField = false;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('fieldEditModal')).hide();
                toastr.success(response.message || 'تم حفظ التعديل');
            }).fail(function (xhr) {
                const errors = Object.values(xhr.responseJSON?.errors ?? {}).flat();
                $('#cso_edit_error').text(errors[0] || xhr.responseJSON?.message || 'تعذر حفظ التعديل. حاول مجدداً.').removeClass('d-none');
            }).always(function () {
                savingField = false;
                $('#saveFieldEdit').prop('disabled', false).text('حفظ التعديل');
                $('#cancelFieldEdit, #fieldEditModal .btn-close').prop('disabled', false);
            });
        });

        let currentStatus = @json($currentStatusName);
        function updateStatusButtons() {
            workspace.find('.status-btn').each(function () {
                const status = this.dataset.status;
                const same = status === 'assigned'
                    ? status === currentStatus && String($('#status_assignee').val() ?? '') === assignedUser
                    : status === currentStatus || (status === 'final_approval' && ['accepted_final', 'final'].includes(currentStatus));
                this.disabled = changingStatus || same;
            });
        }
        let assignedUser = String($('#status_assignee').val() ?? '');
        $('#status_assignee').on('change', function () {
            workspace.find('[data-status="assigned"]').prop('disabled', changingStatus || (currentStatus === 'assigned' && String($(this).val() ?? '') === assignedUser));
        });

        workspace.on('click', '.status-btn', function () {
            if (changingStatus) { return; }
            const button = $(this);
            const nextStatus = button.attr('data-status');
            const assignee = $('#status_assignee').val();
            if (nextStatus === 'assigned' && !assignee) { toastr.error('يرجى اختيار المدقق.'); return; }
            changingStatus = true;
            updateStatusButtons();
            const payload = { _token: @json(csrf_token()), status: nextStatus };
            if (nextStatus === 'assigned') { payload.assigned_to = assignee; }
            $.post(@json($statusRoute), payload).done(function (response) {
                currentStatus = nextStatus;
                if (nextStatus === 'assigned') { assignedUser = String(assignee); }
                $('#cso_current_status').text(statusLabels[nextStatus] ?? nextStatus).attr('class', statusClasses[nextStatus] ?? 'badge badge-light-primary');
                if (response.assignment) {
                    $('#assignment_user_name').text(response.assignment.user_name ?? 'غير مسند');
                    $('#assignment_manager_name').text(response.assignment.manager_name ?? '—');
                    $('#assignment_updated_at').text(response.assignment.updated_at ?? '—');
                }
                toastr.success(response.message || 'تم تحديث الحالة');
            }).fail(function (xhr) {
                toastr.error(xhr.responseJSON?.message || 'تعذر تحديث الحالة');
            }).always(function () {
                changingStatus = false;
                updateStatusButtons();
            });
        });
        applyFieldFilter();
    });
</script>
