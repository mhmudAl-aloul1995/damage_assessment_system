<script>
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('cso-detail');

        if (!root) {
            return;
        }

        const organizationButtons = Array.from(root.querySelectorAll('[data-organization]'));
        const organizationPanels = Array.from(root.querySelectorAll('[data-organization-panel]'));
        const organizationSelect = root.querySelector('#cso-organization-select');
        const organizationSearch = root.querySelector('#cso-organization-search');
        const emptyOrganizationSearch = root.querySelector('#cso-no-organizations');
        const drawerElement = document.getElementById('cso-unit-drawer');
        const drawerTitle = document.getElementById('cso-unit-title');
        const drawerOrganization = document.getElementById('cso-unit-organization');
        const drawerBody = document.getElementById('cso-unit-body');
        let drawerTrigger = null;

        const normalize = (value) => (value || '').toString().trim().toLocaleLowerCase();

        const selectOrganization = (key) => {
            organizationButtons.forEach((button) => {
                const isActive = button.dataset.organization === key;
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            organizationPanels.forEach((panel) => {
                panel.hidden = panel.dataset.organizationPanel !== key;
            });

            if (organizationSelect && organizationSelect.value !== key) {
                organizationSelect.value = key;
            }
        };

        const filterUnits = (panel) => {
            const search = normalize(panel.querySelector('[data-unit-search]')?.value);
            const damage = panel.querySelector('[data-damage-filter]')?.value || '';
            const rows = Array.from(panel.querySelectorAll('[data-unit-row]'));
            let visibleCount = 0;

            rows.forEach((row) => {
                const matchesText = !search || normalize(row.textContent).includes(search);
                const matchesDamage = !damage || row.dataset.damage === damage;
                const visible = matchesText && matchesDamage;
                row.hidden = !visible;
                visibleCount += visible ? 1 : 0;
            });

            const results = panel.querySelector('[data-unit-results]');
            const noResults = panel.querySelector('[data-no-unit-results]');

            if (results) {
                results.textContent = (results.dataset.template || ':count units').replace(':count', visibleCount);
            }

            if (noResults) {
                noResults.hidden = visibleCount > 0 || rows.length === 0;
            }
        };

        organizationButtons.forEach((button) => {
            button.addEventListener('click', () => selectOrganization(button.dataset.organization));
        });

        organizationSelect?.addEventListener('change', () => selectOrganization(organizationSelect.value));

        organizationSearch?.addEventListener('input', () => {
            const query = normalize(organizationSearch.value);
            let visibleCount = 0;

            organizationButtons.forEach((button) => {
                const visible = !query || normalize(button.textContent).includes(query);
                button.hidden = !visible;
                visibleCount += visible ? 1 : 0;
            });

            if (emptyOrganizationSearch) {
                emptyOrganizationSearch.hidden = visibleCount > 0;
            }
        });

        organizationPanels.forEach((panel) => {
            panel.querySelector('[data-unit-search]')?.addEventListener('input', () => filterUnits(panel));
            panel.querySelector('[data-damage-filter]')?.addEventListener('change', () => filterUnits(panel));
            panel.querySelector('[data-clear-filters]')?.addEventListener('click', () => {
                const input = panel.querySelector('[data-unit-search]');
                const select = panel.querySelector('[data-damage-filter]');

                if (input) {
                    input.value = '';
                }

                if (select) {
                    select.value = '';
                }

                filterUnits(panel);
                input?.focus();
            });

            filterUnits(panel);
        });

        root.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-unit-open]');

            if (!trigger || !drawerElement || !drawerBody || !window.bootstrap?.Offcanvas) {
                return;
            }

            const template = document.getElementById(`cso-unit-template-${trigger.dataset.unitOpen}`);

            if (!template) {
                return;
            }

            drawerTrigger = trigger;
            drawerTitle.textContent = template.dataset.title || '';
            drawerOrganization.textContent = template.dataset.organizationName || '';
            drawerBody.replaceChildren(template.content.cloneNode(true));
            drawerBody.scrollTop = 0;
            window.bootstrap.Offcanvas.getOrCreateInstance(drawerElement).show(trigger);
        });

        drawerElement?.addEventListener('hidden.bs.offcanvas', () => {
            drawerBody?.replaceChildren();
            drawerTrigger?.focus();
            drawerTrigger = null;
        });
    });
</script>
