@php
    $resolvedSector = $sector ?? \App\Support\Navigation\SectorNavigation::sectorForCurrentRoute();
    $sectorTitle = $resolvedSector ? \App\Support\Navigation\SectorNavigation::title($resolvedSector) : null;
    $sectorTabs = $resolvedSector ? \App\Support\Navigation\SectorNavigation::forUser($resolvedSector, auth()->user()) : [];
@endphp

@if ($sectorTitle && $sectorTabs !== [])
    @once
        <style>
            .sector-workspace-navigation {
                border: 1px solid var(--bs-gray-200);
                background: var(--bs-body-bg);
            }

            .sector-workspace-navigation .sector-workspace-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: .4rem;
                margin: 0;
                padding: 1rem 1.25rem;
                list-style: none;
            }

            .sector-workspace-navigation .sector-workspace-tab {
                display: inline-flex;
                min-height: 42px;
                flex: 0 0 auto;
                align-items: center;
                gap: .55rem;
                padding: .65rem .9rem;
                border-radius: .65rem;
                color: var(--bs-gray-700);
                font-weight: 600;
                white-space: nowrap;
                transition: background-color .18s ease, color .18s ease;
            }

            .sector-workspace-navigation .sector-workspace-tab:hover {
                background: var(--bs-gray-100);
                color: var(--bs-primary);
            }

            .sector-workspace-navigation .sector-workspace-tab.active {
                background: var(--bs-primary-light);
                color: var(--bs-primary);
            }

            .sector-workspace-navigation .sector-workspace-tab--hud {
                border: 1px solid rgba(var(--bs-warning-rgb), .45);
                background: var(--bs-warning-light);
                color: var(--bs-gray-900);
            }

            .sector-workspace-navigation .sector-workspace-tab--hud:hover,
            .sector-workspace-navigation .sector-workspace-tab--hud.active {
                border-color: var(--bs-warning);
                background: var(--bs-warning);
                color: var(--bs-gray-900);
                box-shadow: 0 .35rem 1rem rgba(var(--bs-warning-rgb), .22);
            }

            .sector-workspace-navigation .sector-workspace-live-dot {
                width: .5rem;
                height: .5rem;
                border-radius: 50%;
                background: #16a34a;
                box-shadow: 0 0 0 .2rem rgba(22, 163, 74, .16);
            }

            .sector-workspace-navigation button.sector-workspace-tab {
                border: 0;
                background-color: transparent;
            }

            .sector-workspace-navigation button.sector-workspace-tab.active {
                background: var(--bs-primary-light);
            }

            .sector-workspace-navigation .sector-report-menu {
                min-width: 260px;
                max-height: 360px;
                overflow-y: auto;
            }

            .sector-workspace-navigation .sector-report-menu .dropdown-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: .7rem .85rem;
                border-radius: .5rem;
            }

        </style>
    @endonce

    <nav class="card card-flush sector-workspace-navigation mb-6" aria-label="{{ __('menu.sector_navigation.aria_label', ['sector' => __($sectorTitle)]) }}">
        <ul class="sector-workspace-tabs">
            @foreach ($sectorTabs as $tab)
                <li>
                    @if ($tab['items'] !== [])
                        <div class="dropdown">
                            <button type="button"
                                class="sector-workspace-tab {{ $tab['is_active'] ? 'active' : '' }}"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ki-duotone {{ $tab['icon'] }} fs-3" aria-hidden="true">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span>{{ __($tab['title']) }}</span>
                                <i class="ki-duotone ki-down fs-5" aria-hidden="true"></i>
                            </button>

                            <ul class="dropdown-menu sector-report-menu">
                                @foreach ($tab['items'] as $report)
                                    <li>
                                        <a class="dropdown-item {{ $report['is_active'] ? 'active' : '' }}"
                                            href="{{ $report['url'] }}"
                                            @if ($report['is_active']) aria-current="page" @endif>
                                            <span>{{ __($report['title']) }}</span>
                                            <i class="ki-duotone ki-left fs-5" aria-hidden="true"></i>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <a @class([
                            'sector-workspace-tab',
                            'active' => $tab['is_active'],
                            'sector-workspace-tab--hud' => ($tab['variant'] ?? null) === 'hud',
                        ])
                            href="{{ $tab['url'] }}"
                            @if ($tab['is_active']) aria-current="page" @endif>
                            <i class="ki-duotone {{ $tab['icon'] }} fs-3" aria-hidden="true">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <span>{{ __($tab['title']) }}</span>
                            @if (($tab['variant'] ?? null) === 'hud')
                                <span class="sector-workspace-live-dot" aria-hidden="true"></span>
                            @endif
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
