<style>
    .cso-detail-workspace { --cso-border: var(--bs-gray-200, #eff2f5); color: var(--bs-gray-900, #181c32); min-width: 0; }
    .cso-detail-workspace .card { border: 1px solid var(--cso-border); box-shadow: 0 2px 8px rgba(0, 0, 0, .025); }
    .cso-detail-workspace .min-w-0 { min-width: 0; }
    .cso-record-header h2, .cso-child-title { overflow-wrap: anywhere; }
    .cso-assignment-summary { display: flex; flex-wrap: wrap; align-items: start; gap: 12px 24px; border-top: 1px solid var(--cso-border); }
    .cso-record-identifiers { max-width: 100%; overflow-wrap: anywhere; }
    .cso-record-identifiers summary, .cso-attachments summary { cursor: pointer; }
    .cso-workspace-tabs { border-bottom: 1px solid var(--cso-border); }
    .cso-workspace-tabs .nav { display: flex; flex-wrap: nowrap; gap: 24px; }
    .cso-workspace-tabs .nav-link { border-top: 0; border-left: 0; border-right: 0; border-bottom: 2px solid transparent; color: var(--bs-gray-600, #7e8299); background: transparent; padding: 20px 0; white-space: nowrap; font-size: 1.05rem; }
    .cso-workspace-tabs .nav-link.active { color: var(--bs-primary, #009ef7); border-bottom-color: var(--bs-primary, #009ef7); }
    .cso-workspace-tabs .nav-link:focus-visible { outline: 2px solid var(--bs-primary, #009ef7); outline-offset: -2px; }
    .cso-field-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; background: var(--bs-gray-100, #f9f9f9); border-bottom: 1px solid var(--cso-border); }
    .cso-field-search { flex: 1 1 260px; max-width: 450px; }
    .cso-field-search input { background: var(--bs-body-bg, #fff); }
    .cso-record-browser { display: grid; grid-template-columns: 245px minmax(0, 1fr); }
    .cso-record-browser.cso-single-record { grid-template-columns: minmax(0, 1fr); }
    .cso-record-list { border-inline-end: 1px solid var(--cso-border); background: var(--bs-gray-100, #f9f9f9); }
    .cso-record-select { display: flex; flex-direction: column; gap: 8px; width: 100%; text-align: start; padding: 16px 12px; border: 1px solid transparent; border-radius: 10px; background: transparent; color: inherit; overflow-wrap: anywhere; }
    .cso-record-select:hover { background: var(--bs-body-bg, #fff); }
    .cso-record-select.active { background: var(--bs-primary-light, #eef6ff); border-color: var(--bs-primary, #009ef7); color: var(--bs-primary, #009ef7); }
    .cso-record-select:focus-visible { outline: 2px solid var(--bs-primary, #009ef7); outline-offset: 2px; }
    .cso-record-details, .cso-record-content { min-width: 0; }
    @media (min-width: 992px) {
        .cso-record-details, #cso_survey_pane > .cso-record-content, .cso-record-list { max-height: min(68vh, 720px); overflow-y: auto; scrollbar-gutter: stable; }
    }
    .cso-child-header { display: flex; justify-content: space-between; align-items: start; gap: 16px; }
    .cso-field-section { border: 1px solid var(--cso-border); border-radius: 10px; overflow: hidden; }
    .cso-section-title { background: var(--bs-gray-100, #f9f9f9); line-height: 1.7; }
    .cso-field-row { display: grid; grid-template-columns: minmax(130px, .8fr) minmax(0, 1.3fr) auto; gap: 16px; align-items: start; border-top: 1px solid var(--cso-border); }
    .cso-field-label { color: var(--bs-gray-600, #7e8299); overflow-wrap: anywhere; line-height: 1.8; }
    .cso-current-value { line-height: 1.8; }
    .cso-wrap-value { overflow-wrap: anywhere; white-space: pre-wrap; }
    .cso-history-item { border-inline-start: 2px solid var(--bs-primary, #009ef7); background: var(--bs-gray-100, #f9f9f9); border-radius: 5px; }
    .cso-detail-workspace [hidden] { display: none !important; }
    .cso-attachment { max-width: 100%; overflow-wrap: anywhere; }
    .cso-attachment img { width: 76px; height: 58px; object-fit: cover; }
    .cso-assignment-controls { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; border-top: 1px solid var(--cso-border); }
    .cso-assignment-controls .form-select, .cso-assignment-controls .select2-container { width: 230px !important; max-width: 100%; }
    @media (max-width: 991.98px) {
        .cso-record-browser { grid-template-columns: minmax(0, 1fr); }
        .cso-record-list { border-inline-end: 0; border-bottom: 1px solid var(--cso-border); }
        .cso-record-list > .d-flex { flex-direction: row !important; overflow-x: auto; padding: 2px; }
        .cso-record-select { flex: 0 0 200px; }
    }
    @media (max-width: 575.98px) {
        .cso-workspace-tabs .nav { gap: 16px; }
        .cso-workspace-tabs .nav-link { font-size: .95rem; }
        .cso-workspace-tabs .badge { margin-inline-start: 3px !important; }
        .cso-field-row { grid-template-columns: minmax(0, 1fr) auto; gap: 8px; }
        .cso-field-label { grid-column: 1; }
        .cso-field-answer { grid-column: 1; grid-row: 2; }
        .cso-field-actions { grid-column: 2; grid-row: 1 / 3; }
        .cso-child-header { flex-direction: column; }
        .cso-record-header .btn { padding: 8px; }
        .cso-field-search { max-width: none; }
        .cso-assignment-controls .select2-container { width: 100% !important; }
    }
</style>
