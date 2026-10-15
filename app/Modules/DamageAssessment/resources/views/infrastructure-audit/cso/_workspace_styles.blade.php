<style>
    .cso-detail-workspace { --cso-border: var(--bs-gray-200, #eff2f5); --cso-app-top: 80px; --cso-header-height: 100px; --cso-sticky-top: calc(var(--cso-app-top) + var(--cso-header-height) + 12px); color: var(--bs-gray-900, #181c32); min-width: 0; }
    .cso-detail-workspace .card { border: 1px solid var(--cso-border); box-shadow: 0 2px 8px rgba(0, 0, 0, .025); }
    .cso-detail-workspace .min-w-0 { min-width: 0; }
    .cso-child-title { overflow-wrap: anywhere; }
    .cso-record-identifiers { max-width: 100%; overflow-wrap: anywhere; }
    .cso-record-identifiers summary { cursor: pointer; }
    .cso-decision-header { position: sticky; top: var(--cso-app-top); z-index: 95; background: var(--bs-body-bg, #fff); }
    .cso-decision-header::before { content: ''; position: absolute; inset: -9px -1px -1px; z-index: -1; background: var(--bs-body-bg, #fff); }
    .cso-decision-content { display: flex; align-items: center; gap: 16px; }
    .cso-decision-identity { flex: 1; min-width: 120px; }
    .cso-decision-identity h2 { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cso-decision-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .cso-decision-actions > .btn, .cso-decision-actions > .dropdown > .btn { white-space: nowrap; }
    .cso-back-link { flex-shrink: 0; }
    .cso-mobile-label { display: none; }
    .cso-assignment-menu { width: 320px; max-width: calc(100vw - 24px); max-height: calc(100dvh - var(--cso-sticky-top) - 16px); overflow-y: auto; }
    .cso-audit-layout { display: grid; grid-template-columns: minmax(0, 1fr) clamp(270px, 25%, 320px); align-items: start; gap: 20px; }
    .cso-audit-data { min-width: 0; }
    .cso-audit-data > .card-body { display: flex; flex-direction: column; height: max(900px, calc(100dvh - var(--cso-panel-top, var(--cso-sticky-top)) - 16px)); min-height: 0; }
    .cso-audit-data .tab-content { flex: 1; min-height: 0; overflow: hidden; }
    .cso-audit-data .tab-pane.active { height: 100%; }
    #cso_survey_pane > .cso-record-content, .cso-record-details { height: 100%; min-height: 0; overflow-y: auto; overscroll-behavior-y: contain; scrollbar-gutter: stable; }
    .cso-attachment-column { position: sticky; top: var(--cso-sticky-top); min-width: 0; }
    .cso-gallery-panel { display: flex; flex-direction: column; max-height: calc(100dvh - var(--cso-panel-top, var(--cso-sticky-top)) - 16px); overflow: hidden; }
    .cso-gallery-header { border-bottom: 1px solid var(--cso-border); }
    .cso-gallery-panel > :not(.cso-gallery-scroll) { flex-shrink: 0; }
    .cso-gallery-preview { position: relative; display: flex; align-items: center; justify-content: center; width: 100%; aspect-ratio: 4 / 3; padding: 0; border: 0; border-radius: 10px; overflow: hidden; background: var(--bs-gray-100, #f9f9f9); cursor: zoom-in; }
    .cso-gallery-preview img { width: 100%; height: 100%; object-fit: contain; }
    .cso-image-expand { position: absolute; inset-inline-end: 8px; bottom: 8px; padding: 5px 8px; border-radius: 6px; background: rgba(24, 28, 50, .78); color: white; font-size: .75rem; }
    .cso-image-error { padding: 20px; color: var(--bs-gray-600, #7e8299); font-size: .8rem; }
    .cso-gallery-scroll { min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
    .cso-gallery-thumbnails { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .cso-image-thumbnail { position: relative; min-height: 86px; padding: 3px; border: 2px solid transparent; border-radius: 8px; background: var(--bs-gray-100, #f9f9f9); overflow: hidden; }
    .cso-image-thumbnail.cso-image-unavailable::before { content: 'صورة غير متاحة'; display: block; padding: 20px 4px; font-size: .7rem; color: var(--bs-gray-600, #7e8299); }
    .cso-image-thumbnail img { width: 100%; height: 76px; object-fit: cover; border-radius: 4px; display: block; }
    .cso-image-thumbnail.active { border-color: var(--bs-primary, #009ef7); }
    .cso-image-thumbnail:focus-visible, .cso-gallery-preview:focus-visible { outline: 2px solid var(--bs-primary, #009ef7); outline-offset: -2px; }
    .cso-image-thumbnail span { position: absolute; bottom: 5px; inset-inline-end: 6px; min-width: 20px; padding: 1px 4px; border-radius: 4px; color: white; background: rgba(24, 28, 50, .75); font-size: .7rem; }
    .cso-attachment-file { display: block; overflow-wrap: anywhere; }
    .cso-mobile-gallery-trigger { display: none; }
    .cso-detail-workspace button, .cso-detail-workspace input, .cso-detail-workspace [role="tabpanel"] { scroll-margin-top: calc(var(--cso-sticky-top) + 16px); }
    .cso-workspace-tabs { position: relative; z-index: 5; flex-shrink: 0; background: var(--bs-body-bg, #fff); border-bottom: 1px solid var(--cso-border); }
    .cso-workspace-tabs::before { content: ''; position: absolute; inset-inline: -1px; bottom: 100%; height: 13px; z-index: -1; background: var(--bs-body-bg, #fff); }
    .cso-workspace-tabs .nav { display: flex; flex-wrap: nowrap; gap: 24px; }
    .cso-workspace-tabs .nav-link { border-top: 0; border-left: 0; border-right: 0; border-bottom: 2px solid transparent; color: var(--bs-gray-600, #7e8299); background: transparent; padding: 20px 0; white-space: nowrap; font-size: 1.05rem; }
    .cso-workspace-tabs .nav-link.active { color: var(--bs-primary, #009ef7); border-bottom-color: var(--bs-primary, #009ef7); }
    .cso-workspace-tabs .nav-link:focus-visible { outline: 2px solid var(--bs-primary, #009ef7); outline-offset: -2px; }
    .cso-field-toolbar { display: flex; flex-wrap: wrap; flex-shrink: 0; align-items: center; gap: 12px; background: var(--bs-gray-100, #f9f9f9); border-bottom: 1px solid var(--cso-border); }
    .cso-field-search { flex: 1 1 260px; max-width: 450px; }
    .cso-field-search input { background: var(--bs-body-bg, #fff); }
    .cso-record-browser { display: grid; grid-template-columns: 180px minmax(0, 1fr); height: 100%; min-height: 0; overflow: hidden; }
    .cso-audit-data .cso-units-pane.active { display: flex; flex-direction: column; }
    .cso-units-pane > .cso-record-browser { flex: 1; height: auto; }
    .cso-unit-context { display: flex; flex-shrink: 0; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; border-bottom: 1px solid var(--cso-border); }
    #cso_unit_organization_name { overflow-wrap: anywhere; }
    .cso-record-list { min-height: 0; height: 100%; overflow-y: auto; overscroll-behavior-y: contain; border-inline-end: 1px solid var(--cso-border); background: var(--bs-gray-100, #f9f9f9); }
    .cso-record-select { display: flex; flex-direction: column; gap: 8px; width: 100%; text-align: start; padding: 16px 12px; border: 1px solid transparent; border-radius: 10px; background: transparent; color: inherit; overflow-wrap: anywhere; }
    .cso-record-select:hover { background: var(--bs-body-bg, #fff); }
    .cso-record-select.active { background: var(--bs-primary-light, #eef6ff); border-color: var(--bs-primary, #009ef7); color: var(--bs-primary, #009ef7); }
    .cso-record-select:focus-visible { outline: 2px solid var(--bs-primary, #009ef7); outline-offset: 2px; }
    .cso-record-details, .cso-record-content { min-width: 0; }
    .cso-child-header { display: flex; justify-content: space-between; align-items: start; gap: 16px; }
    .cso-field-section { border: 1px solid var(--cso-border); border-radius: 10px; overflow: hidden; }
    .cso-section-title { background: var(--bs-gray-100, #f9f9f9); line-height: 1.7; }
    .cso-field-row { display: grid; grid-template-columns: minmax(130px, .8fr) minmax(0, 1.3fr) auto; gap: 16px; align-items: start; border-top: 1px solid var(--cso-border); }
    .cso-field-label { color: var(--bs-gray-600, #7e8299); overflow-wrap: anywhere; line-height: 1.8; }
    .cso-current-value { line-height: 1.8; }
    .cso-field-row.cso-location-row { grid-template-columns: minmax(0, 1fr); }
    .cso-location-row .cso-field-actions { display: none; }
    .cso-location-card { min-width: 0; }
    .cso-location-preview, .cso-location-large-stage { position: relative; overflow: hidden; border: 1px solid var(--cso-border); border-radius: 10px; background: var(--bs-gray-100, #f9f9f9); }
    .cso-location-preview { height: 260px; }
    .cso-location-outline { display: block; width: 100%; height: 100%; fill: var(--bs-primary-light, #eef6ff); stroke: var(--bs-primary, #009ef7); stroke-width: 3px; }
    .cso-location-map { position: absolute; inset: 0; opacity: 0; pointer-events: none; }
    [data-location-ready] .cso-location-map { opacity: 1; }
    .cso-location-source summary { cursor: pointer; }
    .cso-location-source pre { max-height: 200px; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; background: var(--bs-gray-100, #f9f9f9); border-radius: 6px; }
    .cso-location-viewer .modal-content { background: var(--bs-body-bg, #fff); }
    .cso-location-large-stage { height: 65dvh; min-height: 280px; }
    #cso_location_fallback, #cso_location_map { position: absolute; inset: 0; }
    .cso-location-viewer [hidden] { display: none !important; }
    .cso-wrap-value { overflow-wrap: anywhere; white-space: pre-wrap; }
    .cso-history-item { border-inline-start: 2px solid var(--bs-primary, #009ef7); background: var(--bs-gray-100, #f9f9f9); border-radius: 5px; }
    .cso-detail-workspace [hidden] { display: none !important; }
    .cso-assignment-controls { border-top: 1px solid var(--cso-border); padding-top: 16px; }
    .cso-assignment-controls .form-select, .cso-assignment-controls .select2-container { width: 100% !important; max-width: 100%; }
    .cso-image-viewer .modal-content { background: var(--bs-body-bg, #fff); }
    .cso-image-viewer .min-w-0 { min-width: 0; }
    .cso-image-viewer [hidden] { display: none !important; }
    .cso-viewer-controls { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; flex-shrink: 0; }
    .cso-viewer-stage { flex: 1; min-height: 0; overflow: auto; background: var(--bs-gray-100, #f9f9f9); direction: ltr; }
    .cso-viewer-canvas { display: flex; justify-content: center; align-items: center; padding: 20px; width: max-content; min-width: 100%; min-height: 100%; }
    .cso-viewer-canvas img { display: block; max-width: none; object-fit: contain; flex-shrink: 0; }
    .cso-viewer-thumbnails { display: flex; gap: 8px; overflow-x: auto; flex-shrink: 0; }
    .cso-viewer-thumbnails .cso-image-thumbnail { flex: 0 0 90px; min-height: 66px; }
    .cso-viewer-thumbnails .cso-image-thumbnail img { height: 56px; }
    .cso-viewer-files { display: flex; align-items: center; gap: 8px; overflow-x: auto; flex-shrink: 0; }
    .cso-viewer-files a { white-space: nowrap; }
    .cso-image-viewer .modal-header { flex-shrink: 0; }
    @media (max-width: 1399.98px) {
        .cso-decision-content { flex-wrap: wrap; }
        .cso-decision-identity { flex-basis: calc(100% - 110px); }
        .cso-back-link { order: 1; }
        .cso-decision-actions { order: 2; width: 100%; }
    }
    @media (max-width: 1199.98px) {
        .cso-audit-layout { grid-template-columns: minmax(0, 1fr); }
        .cso-audit-data > .card-body { height: max(360px, calc(100dvh - var(--cso-panel-top, var(--cso-sticky-top)) - 16px)); }
        .cso-attachment-column { display: none; }
        .cso-mobile-gallery-trigger { display: inline-flex; align-items: center; position: fixed; inset-inline-start: 16px; bottom: calc(16px + env(safe-area-inset-bottom)); z-index: 94; }
        .cso-detail-workspace { padding-bottom: 65px; }
    }
    @media (max-width: 991.98px) {
        .cso-record-browser { grid-template-columns: minmax(0, 1fr); grid-template-rows: auto minmax(0, 1fr); }
        .cso-record-list { height: auto; overflow-y: visible; border-inline-end: 0; border-bottom: 1px solid var(--cso-border); }
        .cso-record-list-help { display: none; }
        .cso-record-list > .d-flex { flex-direction: row !important; overflow-x: auto; padding: 2px; }
        .cso-record-select { flex: 0 0 200px; }
    }
    @media (max-width: 575.98px) {
        .cso-location-row .cso-field-answer { grid-column: auto; grid-row: auto; }
        .cso-location-preview { height: 220px; }
        .cso-location-viewer .modal-body { min-height: 0; }
        .cso-location-large-stage { flex: 1; height: auto; min-height: 0; }
        .cso-workspace-tabs .nav { gap: 16px; }
        .cso-workspace-tabs .nav-link { font-size: .95rem; }
        .cso-workspace-tabs .badge { margin-inline-start: 3px !important; }
        .cso-field-row { grid-template-columns: minmax(0, 1fr) auto; gap: 8px; }
        .cso-field-label { grid-column: 1; }
        .cso-field-answer { grid-column: 1; grid-row: 2; }
        .cso-field-actions { grid-column: 2; grid-row: 1 / 3; }
        .cso-child-header { flex-direction: column; }
        .cso-desktop-label { display: none; }
        .cso-mobile-label { display: inline; }
        .cso-decision-content { gap: 10px; }
        .cso-decision-actions { flex-wrap: nowrap; justify-content: space-between; }
        .cso-decision-actions > .btn, .cso-decision-actions > .dropdown > .btn { padding: 8px 10px !important; }
        .cso-decision-identity h2 { font-size: 1.05rem !important; }
        .cso-field-complete { display: none; }
        .cso-field-search { max-width: none; }
        .cso-assignment-controls .select2-container { width: 100% !important; }
    }
</style>
