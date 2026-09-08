<style>
    .cso-detail {
        --cso-border: #e5e7eb;
        --cso-muted: #6b7280;
        --cso-text: #1f2937;
        --cso-soft: #f8fafc;
        color: var(--cso-text);
        letter-spacing: 0;
    }

    .cso-detail [hidden] {
        display: none !important;
    }

    .cso-heading {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: start;
        padding: 24px 28px;
        background: #fff;
        border-bottom: 1px solid var(--cso-border);
    }

    .cso-heading-main {
        display: flex;
        gap: 14px;
        min-width: 0;
    }

    .cso-back {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        border: 1px solid var(--cso-border);
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #374151;
        background: #fff;
    }

    html[dir="rtl"] .cso-back i {
        transform: rotate(180deg);
    }

    .cso-heading h1 {
        margin: 0 0 7px;
        font-size: 22px;
        line-height: 1.35;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .cso-heading-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 14px;
        color: var(--cso-muted);
        font-size: 13px;
    }

    .cso-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(110px, 1fr));
        gap: 0;
        min-width: min(100%, 720px);
        border: 1px solid var(--cso-border);
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
    }

    .cso-summary dt,
    .cso-summary dd {
        margin: 0;
    }

    .cso-summary-item {
        padding: 12px 14px;
        border-inline-end: 1px solid var(--cso-border);
    }

    .cso-summary-item:last-child {
        border-inline-end: 0;
    }

    .cso-summary dt {
        color: var(--cso-muted);
        font-size: 12px;
        margin-bottom: 5px;
    }

    .cso-summary dd {
        font-size: 14px;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .cso-chip {
        display: inline-flex;
        align-items: center;
        min-height: 26px;
        padding: 3px 9px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    .cso-chip[data-damage="fully_damaged"],
    .cso-damage-card[data-damage="fully_damaged"] {
        color: #a02e35;
        background: #fff0f0;
    }

    .cso-chip[data-damage="partially_damaged"],
    .cso-damage-card[data-damage="partially_damaged"] {
        color: #785400;
        background: #fff8db;
    }

    .cso-chip[data-damage="no_damage"],
    .cso-damage-card[data-damage="no_damage"] {
        color: #17653e;
        background: #e9f7ef;
    }

    .cso-chip[data-damage="committee_review"],
    .cso-damage-card[data-damage="committee_review"] {
        color: #215b89;
        background: #ebf4fc;
    }

    .cso-chip[data-damage="unclassified"],
    .cso-damage-card[data-damage="unclassified"] {
        color: #505864;
        background: #f0f1f3;
    }

    .cso-tabs {
        padding: 0 28px;
        background: #fff;
        border-bottom: 1px solid var(--cso-border);
    }

    .cso-tabs .nav-link {
        border: 0;
        border-bottom: 3px solid transparent;
        border-radius: 0;
        padding: 15px 4px 12px;
        margin-inline-end: 24px;
        color: #4b5563;
        font-size: 14px;
        font-weight: 700;
    }

    .cso-tabs .nav-link.active {
        color: #1b84ff;
        border-bottom-color: #1b84ff;
        background: transparent;
    }

    .cso-tab-pane {
        background: #fff;
    }

    .cso-survey-sections,
    .cso-organization-content {
        padding: 26px 28px;
    }

    .cso-workspace {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 252px;
        grid-template-areas: "content organizations";
        min-height: 520px;
        direction: ltr;
    }

    .cso-workspace > * {
        direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }};
    }

    .cso-organizations {
        grid-area: organizations;
        border-inline-start: 1px solid var(--cso-border);
        padding: 22px 18px;
        background: var(--cso-soft);
    }

    .cso-organizations h2,
    .cso-organization-panel h3 {
        margin: 0;
        font-size: 18px;
        line-height: 1.45;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .cso-organization-list {
        display: grid;
        gap: 6px;
        max-height: 65vh;
        overflow: auto;
        margin-top: 12px;
    }

    .cso-organization-button {
        width: 100%;
        min-height: 56px;
        border: 1px solid transparent;
        border-inline-start: 3px solid transparent;
        border-radius: 6px;
        background: transparent;
        padding: 9px 10px;
        color: var(--cso-text);
        text-align: start;
        transition: background-color .15s ease, border-color .15s ease;
    }

    .cso-organization-button[aria-pressed="true"] {
        background: #fff;
        border-color: var(--cso-border);
        border-inline-start-color: #1b84ff;
    }

    .cso-organization-button strong,
    .cso-organization-button span {
        display: block;
        overflow-wrap: anywhere;
    }

    .cso-organization-button span {
        color: var(--cso-muted);
        font-size: 12px;
        margin-top: 3px;
    }

    .cso-mobile-selector {
        display: none;
        padding: 16px 18px;
        border-bottom: 1px solid var(--cso-border);
        background: var(--cso-soft);
    }

    .cso-organization-content {
        grid-area: content;
        min-width: 0;
    }

    .cso-eyebrow {
        color: var(--cso-muted);
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .cso-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 18px;
        margin: 10px 0 18px;
        color: #374151;
        font-size: 13px;
    }

    .cso-meta-row dt,
    .cso-meta-row dd {
        display: inline;
        margin: 0;
    }

    .cso-meta-row dt {
        color: var(--cso-muted);
        margin-inline-end: 5px;
    }

    .cso-organization-details {
        margin: 0 0 22px;
        border-block: 1px solid var(--cso-border);
    }

    .cso-organization-details > summary,
    .cso-section > summary {
        cursor: pointer;
        list-style: none;
        font-weight: 700;
        color: #263446;
    }

    .cso-organization-details > summary {
        padding: 13px 0;
    }

    .cso-damage-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 8px;
        margin: 18px 0 24px;
    }

    .cso-damage-card {
        border: 1px solid rgba(31, 41, 55, .08);
        border-radius: 6px;
        padding: 10px 12px;
        min-height: 68px;
    }

    .cso-damage-card dt,
    .cso-damage-card dd {
        margin: 0;
    }

    .cso-damage-card dt {
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 7px;
    }

    .cso-damage-card dd {
        font-size: 20px;
        font-weight: 800;
    }

    .cso-unit-tools {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(170px, .65fr) 38px;
        gap: 8px;
        align-items: center;
        margin: 12px 0 14px;
    }

    .cso-icon-button {
        width: 38px;
        height: 38px;
        border: 1px solid var(--cso-border);
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        color: #374151;
    }

    .cso-table-scroll {
        overflow-x: auto;
    }

    .cso-units-table,
    .cso-fields-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .cso-units-table {
        min-width: 610px;
        border-block: 1px solid var(--cso-border);
    }

    .cso-units-table th,
    .cso-units-table td,
    .cso-fields-table th,
    .cso-fields-table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--cso-border);
        vertical-align: top;
        overflow-wrap: anywhere;
        font-size: 13px;
        line-height: 1.7;
    }

    .cso-units-table th,
    .cso-fields-table th {
        color: #374151;
        font-weight: 700;
    }

    .cso-units-table thead th {
        color: var(--cso-muted);
        font-size: 12px;
        background: var(--cso-soft);
    }

    .cso-unit-name {
        border: 0;
        padding: 0;
        background: transparent;
        color: #1b84ff;
        font-weight: 700;
        text-align: start;
    }

    .cso-section {
        border-block-end: 1px solid var(--cso-border);
    }

    .cso-section > summary {
        padding: 15px 0;
        font-size: 15px;
    }

    .cso-section-body {
        padding-bottom: 14px;
    }

    .cso-fields-table th {
        width: 52%;
    }

    .cso-unit-drawer {
        --bs-offcanvas-width: min(720px, 100vw);
        width: min(720px, 100vw) !important;
    }

    .cso-unit-drawer .offcanvas-header,
    .cso-unit-drawer .offcanvas-body {
        padding: 24px;
    }

    .cso-unit-drawer h3 {
        margin: 0;
        font-size: 20px;
        line-height: 1.4;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .cso-muted {
        color: var(--cso-muted);
    }

    .cso-empty {
        padding: 26px 0;
        color: var(--cso-muted);
    }

    .cso-detail :focus-visible {
        outline: 3px solid rgba(27, 132, 255, .3);
        outline-offset: 2px;
    }

    @media (max-width: 991.98px) {
        .cso-heading {
            grid-template-columns: 1fr;
        }

        .cso-summary {
            min-width: 0;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cso-workspace {
            display: block;
        }

        .cso-organizations {
            display: none;
        }

        .cso-mobile-selector {
            display: block;
        }
    }

    @media (max-width: 575.98px) {
        .cso-heading,
        .cso-survey-sections,
        .cso-organization-content {
            padding: 18px;
        }

        .cso-heading h1 {
            font-size: 18px;
        }

        .cso-heading-main {
            gap: 10px;
        }

        .cso-summary {
            grid-template-columns: 1fr;
        }

        .cso-summary-item {
            border-inline-end: 0;
            border-bottom: 1px solid var(--cso-border);
        }

        .cso-summary-item:last-child {
            border-bottom: 0;
        }

        .cso-tabs {
            padding: 0 18px;
        }

        .cso-tabs .nav-link {
            max-width: calc(50vw - 20px);
            margin-inline-end: 12px;
            white-space: normal;
        }

        .cso-damage-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .cso-unit-tools {
            grid-template-columns: minmax(0, 1fr) 38px;
        }

        .cso-unit-tools select {
            grid-column: 1 / -1;
        }

        .cso-units-table {
            min-width: 490px;
        }
    }
</style>
