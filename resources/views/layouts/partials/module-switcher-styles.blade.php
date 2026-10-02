<style>
    .phc-module-switcher {
        flex-shrink: 0;
        margin: 1.2rem 1rem .35rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(148, 163, 184, .16);
    }
    .phc-module-eyebrow {
        margin: 0 .35rem .6rem;
        color: #94a3b8;
        font-size: .75rem;
        font-weight: 600;
    }
    .phc-module-trigger {
        display: flex;
        align-items: center;
        gap: .7rem;
        width: 100%;
        min-height: 60px;
        padding: .65rem .75rem;
        border: 1px solid #38465c;
        border-radius: .85rem;
        background: #1c283b;
        color: #f8fafc;
        text-align: start;
        transition: border-color .15s, background .15s;
    }
    .phc-module-trigger:hover:not(:disabled), .phc-module-trigger[aria-expanded="true"] {
        background: #25344b;
        border-color: #60a5fa;
    }
    .phc-module-trigger:focus-visible, .phc-module-option:focus-visible {
        outline: 2px solid #60a5fa;
        outline-offset: 3px;
    }
    .phc-module-icon, .phc-module-option-icon {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: .65rem;
    }
    .phc-module-icon { background: #244164; }
    .phc-module-icon i { color: #93c5fd; }
    .phc-module-name {
        flex: 1;
        min-width: 0;
        font-size: .9rem;
        font-weight: 700;
        line-height: 1.8;
    }
    .phc-module-chevron { flex-shrink: 0; color: #94a3b8; }
    .phc-module-options.dropdown-menu {
        z-index: 1100;
        width: 340px;
        max-width: calc(100vw - 24px);
        max-height: min(650px, calc(100dvh - 170px));
        overflow-y: auto;
        padding: .45rem;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 18px 50px rgba(15, 23, 42, .22);
        text-align: start;
    }
    .phc-module-panel-header { padding: .9rem .8rem .8rem; border-bottom: 1px solid #edf2f7; }
    .phc-module-panel-header strong { display: block; color: #172033; font-size: 1rem; }
    .phc-module-panel-header span { display: block; margin-top: .35rem; color: #64748b; font-size: .75rem; }
    .phc-module-group-label { padding: .85rem .8rem .4rem; color: #64748b; font-size: .72rem; font-weight: 700; }
    .phc-module-option.dropdown-item {
        display: flex;
        align-items: center;
        gap: .75rem;
        min-height: 74px;
        padding: .8rem;
        border-radius: .7rem;
        white-space: normal;
        color: #172033;
    }
    .phc-module-option.dropdown-item:hover, .phc-module-option.dropdown-item:focus { background: #f1f5f9; color: #172033; }
    .phc-module-option-icon { background: #f1f5f9; }
    .phc-module-option-icon i { color: #52647d; }
    .phc-module-option-copy { flex: 1; min-width: 0; }
    .phc-module-option-copy strong { display: block; font-size: .85rem; font-weight: 700; line-height: 1.7; }
    .phc-module-option-copy>span { display: block; margin-top: .2rem; color: #64748b; font-size: .72rem; line-height: 1.7; }
    .phc-module-option-current.dropdown-item, .phc-module-option-current.dropdown-item:hover { background: #eff6ff; }
    .phc-module-option-current .phc-module-option-icon { background: #dbeafe; }
    .phc-module-option-current .phc-module-option-icon i, .phc-module-option-current strong { color: #1d4ed8; }
    .phc-module-current-mark { display: flex; flex-shrink: 0; color: #2563eb; }
    .phc-module-panel-footer { margin-top: .6rem; padding: .75rem .8rem .4rem; border-top: 1px solid #edf2f7; color: #64748b; font-size: .7rem; line-height: 1.8; }
    @media (min-width: 992px) {
        body[data-kt-app-sidebar-minimize="on"] .phc-module-switcher { margin-inline: .45rem; }
        body[data-kt-app-sidebar-minimize="on"] .phc-module-trigger { justify-content: center; padding-inline: .35rem; }
        body[data-kt-app-sidebar-minimize="on"] .phc-module-eyebrow,
        body[data-kt-app-sidebar-minimize="on"] .phc-module-name,
        body[data-kt-app-sidebar-minimize="on"] .phc-module-chevron { display: none; }
        body[data-kt-app-sidebar-minimize="on"] .app-sidebar:hover .phc-module-eyebrow,
        body[data-kt-app-sidebar-minimize="on"] .app-sidebar:hover .phc-module-name,
        body[data-kt-app-sidebar-minimize="on"] .app-sidebar:hover .phc-module-chevron { display: block; }
    }
    @media (max-width: 991.98px) {
        .phc-module-options.dropdown-menu { width: 205px; max-height: calc(100dvh - 180px); }
        .phc-module-option.dropdown-item { gap: .5rem; padding: .65rem .5rem; }
        .phc-module-panel-header { padding-inline: .5rem; }
        .phc-module-panel-header strong { font-size: .9rem; }
    }
</style>
