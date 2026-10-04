<script>
document.addEventListener('DOMContentLoaded', () => {
    const translations = {{ Illuminate\Support\Js::from(__('access')) }};
    const capabilities = {{ Illuminate\Support\Js::from($can) }};
    const canViewUsers = {{ Illuminate\Support\Js::from($canViewUsers) }};
    const grantable = {{ Illuminate\Support\Js::from($grantablePermissions) }};
    const storeUrl = {{ Illuminate\Support\Js::from(route('roles.store')) }};
    let roles = {{ Illuminate\Support\Js::from($roleData) }};
    const root = document.getElementById('access-center');
    const find = selector => root.querySelector(selector);
    const all = selector => [...root.querySelectorAll(selector)];
    const toggles = all('.permission-toggle');
    const labels = new Map(toggles.map(input => [input.value, input.dataset.label]));
    const reviewModal = new bootstrap.Modal(find('#role-review'));
    const membersModal = new bootstrap.Modal(find('#role-members'));
    let currentRole = null;
    let originalPermissions = [];
    let originalName = '';
    let writable = false;
    let saving = false;
    let dirty = false;
    let openSequence = 0;
    let memberSequence = 0;

    function element(tag, className, text) {
        const node = document.createElement(tag);
        node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function feedback(message, success = false) {
        const box = find('#access-feedback');
        box.className = 'alert ' + (success ? 'alert-success' : 'alert-danger');
        box.textContent = message;
        box.hidden = false;
        box.focus();
    }

    async function request(url, method = 'GET', body) {
        const response = await fetch(url, {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            ...(body === undefined ? {} : {body: JSON.stringify(body)})
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || translations.error));
        return data;
    }

    function canManage(role) {
        return !role.protected && (grantable === null || role.permissions.every(name => grantable.includes(name)));
    }

    function renderRoles() {
        const query = find('#role-search').value.trim().toLocaleLowerCase();
        const filter = find('#role-filter').value;
        const cards = find('#role-cards');
        cards.replaceChildren();
        roles.filter(role => role.name.toLocaleLowerCase().includes(query) &&
            (filter === 'all' || role.system === (filter === 'system')))
            .sort((a, b) => a.name.localeCompare(b.name))
            .forEach(role => {
                const card = element('article', 'card h-100');
                const body = element('div', 'card-body');
                const heading = element('div', 'd-flex flex-wrap justify-content-between gap-3 mb-5');
                heading.append(element('h3', 'fs-4 mb-0 text-break', role.name),
                    element('span', 'badge ' + (role.system ? 'badge-light-warning' : 'badge-light-primary'), role.system ? translations.system : translations.custom));
                body.append(heading, element('p', 'text-muted', role.users_count + ' ' + translations.users + ' · ' + role.permissions.length + ' ' + translations.permissions));
                const groups = new Map();
                role.permissions.forEach(name => {
                    const input = toggles.find(input => input.value === name);
                    if (input) groups.set(input.dataset.group, all('.access-group').find(button => button.dataset.group === input.dataset.group).firstElementChild.textContent);
                });
                const badges = element('div', 'd-flex flex-wrap gap-2 mb-6');
                [...groups.values()].slice(0, 4).forEach(label => badges.append(element('span', 'badge badge-light', label)));
                if (groups.size > 4) badges.append(element('span', 'badge badge-light', '+' + (groups.size - 4)));
                body.append(badges);
                const buttons = element('div', 'd-flex flex-wrap gap-2');
                const openButton = element('button', 'btn btn-sm btn-light-primary', capabilities.update && canManage(role) ? translations.edit : translations.view);
                openButton.type = 'button';
                openButton.addEventListener('click', () => openRole(role));
                buttons.append(openButton);
                if (capabilities.create) {
                    const duplicate = element('button', 'btn btn-sm btn-light', translations.copy);
                    duplicate.type = 'button';
                    duplicate.disabled = grantable !== null && role.permissions.some(name => !grantable.includes(name));
                    duplicate.addEventListener('click', () => openRole(role, true));
                    buttons.append(duplicate);
                }
                if (canViewUsers) {
                    const members = element('button', 'btn btn-sm btn-light', translations.members);
                    members.type = 'button';
                    members.addEventListener('click', () => { membersModal.show(); loadMembers(role.members_url); });
                    buttons.append(members);
                }
                if (capabilities.delete && !role.system && role.users_count === 0 && canManage(role)) {
                    const remove = element('button', 'btn btn-sm btn-light-danger', translations.delete);
                    remove.type = 'button';
                    remove.addEventListener('click', async () => {
                        if (!window.confirm(translations.delete_confirm + '\n' + role.name)) return;
                        remove.disabled = true;
                        try {
                            const result = await request(role.delete_url, 'DELETE');
                            roles = roles.filter(item => item.id !== role.id);
                            renderRoles();
                            feedback(result.message, true);
                        } catch (error) { feedback(error.message); remove.disabled = false; }
                    });
                    buttons.append(remove);
                }
                body.append(buttons);
                card.append(body);
                cards.append(card);
            });
        find('#roles-empty').hidden = cards.childElementCount > 0;
        find('#role-count').textContent = roles.length;
    }

    function selected() { return toggles.filter(input => input.checked).map(input => input.value); }

    function changes() {
        const values = selected();
        return {added: values.filter(name => !originalPermissions.includes(name)), removed: originalPermissions.filter(name => !values.includes(name))};
    }

    function selectGroup(key) {
        all('.access-group').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.group === key)));
        all('.permission-group').forEach(group => { group.hidden = group.dataset.group !== key; });
    }

    function refreshSelection() {
        const query = find('#permission-search').value.trim().toLocaleLowerCase();
        const selectedOnly = find('#selected-only').checked;
        toggles.forEach(input => {
            input.closest('.permission-cell').hidden = !input.closest('.permission-cell').dataset.search.includes(query) || (selectedOnly && !input.checked);
        });
        all('.permission-row').forEach(row => {
            row.hidden = [...row.querySelectorAll('.permission-cell')].every(cell => cell.hidden);
        });
        all('.permission-group').forEach(group => {
            const inputs = [...group.querySelectorAll('.permission-toggle')];
            const checked = inputs.filter(input => input.checked).length;
            const button = all('.access-group').find(button => button.dataset.group === group.dataset.group);
            button.querySelector('.group-count').textContent = checked + ' / ' + inputs.length;
            const visible = inputs.filter(input => !input.closest('.permission-cell').hidden && !input.disabled);
            const groupToggle = group.querySelector('.select-visible');
            if (groupToggle) {
                groupToggle.disabled = visible.length === 0;
                groupToggle.checked = visible.length > 0 && visible.every(input => input.checked);
                groupToggle.indeterminate = visible.some(input => input.checked) && !groupToggle.checked;
            }
            const empty = group.querySelector('.permission-empty');
            if (empty) empty.hidden = inputs.some(input => !input.closest('.permission-cell').hidden);
        });
        const diff = changes();
        dirty = writable && (diff.added.length > 0 || diff.removed.length > 0 || find('#role-name').value.trim() !== originalName);
        find('#selection-summary').textContent = selected().length + ' ' + translations.selected;
        find('#changes-summary').textContent = dirty ? translations.changes + ' · +' + diff.added.length + ' / −' + diff.removed.length : translations.no_changes;
        find('#review-role').disabled = !dirty || saving;
        find('#review-role').hidden = !writable;
        find('#discard-role').hidden = !writable;
    }

    async function openRole(role = null, copy = false) {
        if (dirty && !window.confirm(translations.leave)) return;
        const sequence = ++openSequence;
        find('#access-feedback').hidden = true;
        try {
            if (role) {
                role = (await request(role.edit_url)).role;
                if (sequence !== openSequence) return;
            }
            currentRole = copy ? null : role;
            writable = currentRole ? capabilities.update && canManage(currentRole) : capabilities.create;
            originalPermissions = copy ? [] : (role?.permissions || []);
            originalName = copy ? '' : (role?.name || '');
            const chosen = role?.permissions || [];
            toggles.forEach(input => {
                input.checked = chosen.includes(input.value);
                input.disabled = !writable || (grantable !== null && !grantable.includes(input.value));
            });
            find('#role-name').value = copy ? role.name + ' — ' + translations.copy_suffix : originalName;
            find('#role-name').readOnly = !writable || Boolean(currentRole?.system);
            find('#editor-heading').textContent = currentRole ? currentRole.name : translations.new_role;
            const notice = find('#role-notice');
            notice.hidden = false;
            notice.textContent = currentRole?.protected ? translations.admin_protected : !writable ? translations.read_only : currentRole?.system ? translations.system_notice : copy && role.system ? translations.copy_notice : '';
            if (!notice.textContent) notice.hidden = true;
            find('#permission-search').value = '';
            find('#selected-only').checked = false;
            find('#role-list').hidden = true;
            find('#role-editor').hidden = false;
            selectGroup(toggles.find(input => input.checked)?.dataset.group || all('.access-group')[0].dataset.group);
            refreshSelection();
            find('#role-name').focus();
        } catch (error) { feedback(error.message); }
    }

    function closeEditor(force = false) {
        if (saving || (!force && dirty && !window.confirm(translations.leave))) return;
        dirty = false;
        currentRole = null;
        find('#role-editor').hidden = true;
        find('#role-list').hidden = false;
        find('#role-search').focus();
    }

    find('#role-search').addEventListener('input', renderRoles);
    find('#role-filter').addEventListener('change', renderRoles);
    find('#create-role')?.addEventListener('click', () => openRole());
    find('#back-to-roles').addEventListener('click', () => closeEditor());
    find('#discard-role').addEventListener('click', () => closeEditor());
    find('#role-name').addEventListener('input', refreshSelection);
    find('#permission-search').addEventListener('input', () => {
        refreshSelection();
        const current = all('.permission-group').find(group => !group.hidden);
        const hasVisible = group => [...group.querySelectorAll('.permission-cell')].some(cell => !cell.hidden);
        if (current && !hasVisible(current)) {
            const match = all('.permission-group').find(hasVisible);
            if (match) selectGroup(match.dataset.group);
        }
    });
    find('#selected-only').addEventListener('change', refreshSelection);
    find('#show-codes').addEventListener('change', event => root.classList.toggle('show-codes', event.target.checked));
    all('.access-group').forEach(button => button.addEventListener('click', () => selectGroup(button.dataset.group)));
    toggles.forEach(input => input.addEventListener('change', refreshSelection));
    all('.select-visible').forEach(input => input.addEventListener('change', () => {
        input.closest('.permission-group').querySelectorAll('.permission-toggle').forEach(toggle => {
            if (!toggle.disabled && !toggle.closest('.permission-cell').hidden) toggle.checked = input.checked;
        });
        refreshSelection();
    }));
    find('#review-role').addEventListener('click', () => {
        if (!find('#role-name').reportValidity() || !dirty) return;
        const diff = changes();
        ['added', 'removed'].forEach(type => {
            const list = find('#review-' + type);
            list.replaceChildren();
            diff[type].forEach(name => list.append(element('li', 'py-1', labels.get(name) || name)));
            if (!diff[type].length) list.append(element('li', 'text-muted', '—'));
        });
        find('#review-impact').textContent = translations.affected + ': ' + (currentRole?.users_count || 0);
        find('#review-name').textContent = translations.name + ': ' + find('#role-name').value.trim();
        find('#review-error').hidden = true;
        reviewModal.show();
    });
    find('#role-review').addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    find('#confirm-role').addEventListener('click', async () => {
        if (saving || !writable || !dirty) return;
        saving = true;
        find('#confirm-role').disabled = true;
        find('#confirm-role').textContent = translations.saving;
        try {
            const data = await request(currentRole ? currentRole.update_url : storeUrl, currentRole ? 'PUT' : 'POST', {
                name: find('#role-name').value.trim(),
                permissions: selected(),
                ...(currentRole ? {revision: currentRole.revision} : {})
            });
            roles = roles.filter(role => role.id !== data.role.id);
            roles.push(data.role);
            saving = false;
            dirty = false;
            reviewModal.hide();
            closeEditor(true);
            renderRoles();
            feedback(data.message, true);
        } catch (error) {
            find('#review-error').textContent = error.message;
            find('#review-error').hidden = false;
        } finally {
            saving = false;
            find('#confirm-role').disabled = false;
            find('#confirm-role').textContent = translations.confirm;
        }
    });

    async function loadMembers(url) {
        const sequence = ++memberSequence;
        const container = find('#members-body');
        container.textContent = translations.loading;
        find('#members-next').disabled = true;
        find('#members-previous').disabled = true;
        try {
            const data = await request(url);
            if (sequence !== memberSequence) return;
            container.replaceChildren();
            data.data.forEach(user => {
                const row = element('div', 'd-flex justify-content-between align-items-center gap-3 border-bottom py-4');
                const info = element('div', 'text-break');
                info.append(element('strong', 'd-block', user.name), element('span', 'text-muted', user.email));
                const link = element('a', 'btn btn-sm btn-light-primary flex-shrink-0', translations.view_access);
                link.href = user.url;
                row.append(info, link);
                container.append(row);
            });
            if (!data.data.length) container.textContent = translations.no_members;
            [['next', data.next_page_url], ['previous', data.prev_page_url]].forEach(([key, page]) => {
                const button = find('#members-' + key);
                button.disabled = !page;
                button.onclick = () => loadMembers(page);
            });
        } catch (error) { if (sequence === memberSequence) container.textContent = error.message; }
    }
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
    renderRoles();
});
</script>
