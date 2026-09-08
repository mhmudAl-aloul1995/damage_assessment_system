<style>
    .cso-detail,
    .cso-unit-drawer {
        --cso-muted: var(--bs-gray-600);
        color: var(--bs-gray-900);
    }

    .cso-detail [hidden] {
        display: none !important;
    }

    .cso-detail .card,
    .cso-detail .tab-content,
    .cso-organization-content {
        min-width: 0;
    }

    .cso-heading {
        border-top: 3px solid var(--bs-primary);
    }

    .cso-heading h1,
    .cso-heading-meta,
    .cso-organization-button,
    .cso-organization-panel h3,
    .cso-unit-drawer h3 {
        overflow-wrap: anywhere;
    }

    html[dir="rtl"] .cso-back i {
        transform: rotate(180deg);
    }

    .cso-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 1rem;
    }

    .cso-summary dd {
        overflow-wrap: anywhere;
    }

    .cso-workspace {
        display: grid;
        grid-template-columns: 280px minmax(0, 1fr);
        align-items: start;
        gap: 1.5rem;
    }

    .cso-organization-list {
        max-height: 65vh;
        overflow-y: auto;
    }

    .cso-organization-button {
        text-align: start;
        white-space: normal;
        border: 1px solid transparent;
    }

    .cso-organization-button[aria-pressed="true"] {
        color: var(--bs-primary);
        background: var(--bs-primary-light);
        border-color: var(--bs-primary);
    }

    .cso-organization-button[aria-pressed="true"] .symbol-label {
        color: var(--bs-primary) !important;
        background: var(--bs-body-bg) !important;
    }

    .cso-organization-button strong {
        line-height: 1.8;
    }

    .cso-damage-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .75rem;
    }

    .cso-damage-card {
        border-inline-start: 3px solid currentColor;
    }

    .cso-detail [data-damage="partially_damaged"].badge,
    .cso-detail .cso-damage-card[data-damage="partially_damaged"],
    .cso-unit-drawer [data-damage="partially_damaged"].badge {
        color: var(--bs-warning-text-emphasis) !important;
    }

    .cso-detail .table-responsive {
        position: relative;
    }

    .cso-unit-tools {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(160px, .65fr) auto;
        gap: .75rem;
        align-items: center;
    }

    .cso-search-icon {
        position: absolute;
        inset-inline-start: 1rem;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .cso-unit-name {
        text-align: start;
        white-space: normal;
    }

    .cso-units-table {
        min-width: 620px;
    }

    .cso-fields-table {
        table-layout: fixed;
    }

    .cso-fields-table th {
        width: 52%;
    }

    .cso-fields-table th,
    .cso-fields-table td,
    .cso-units-table td {
        overflow-wrap: anywhere;
        line-height: 1.8;
    }

    .cso-section > summary,
    .cso-organization-details > summary {
        cursor: pointer;
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .cso-section > summary::-webkit-details-marker,
    .cso-organization-details > summary::-webkit-details-marker {
        display: none;
    }

    .cso-section[open] > summary .cso-chevron,
    .cso-organization-details[open] > summary .cso-chevron {
        transform: rotate(180deg);
    }

    .cso-unit-drawer {
        --bs-offcanvas-width: min(720px, 100vw);
        width: min(720px, 100vw) !important;
        background: var(--bs-body-bg);
    }

    .cso-muted {
        color: var(--cso-muted);
    }

    .cso-empty {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--cso-muted);
    }

    .cso-detail :focus-visible,
    .cso-unit-drawer :focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: 3px;
    }

    @media (max-width: 1199.98px) {
        .cso-workspace {
            grid-template-columns: 240px minmax(0, 1fr);
        }

        .cso-damage-summary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .cso-summary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .cso-workspace {
            display: block;
        }
    }

    @media (max-width: 575.98px) {
        .cso-summary,
        .cso-damage-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cso-summary > :first-child {
            grid-column: 1 / -1;
        }

        .cso-unit-tools {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .cso-unit-tools > :first-child {
            grid-column: 1 / -1;
        }

        .cso-tabs .nav-link {
            font-size: .95rem !important;
        }
    }
</style>
