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

            .sector-workspace-navigation .sector-workspace-title {
                display: flex;
                align-items: center;
                gap: .75rem;
                padding: 1.15rem 1.5rem .85rem;
                color: var(--bs-gray-900);
                font-size: 1.05rem;
                font-weight: 700;
            }

            .sector-workspace-navigation .sector-workspace-title-icon {
                display: inline-flex;
                width: 34px;
                height: 34px;
                align-items: center;
                justify-content: center;
                border-radius: .6rem;
                background: var(--bs-primary-light);
                color: var(--bs-primary);
            }

            .sector-workspace-navigation .sector-workspace-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: .4rem;
                margin: 0;
                padding: 0 1.25rem 1rem;
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
        <div class="sector-workspace-title">
            <span class="sector-workspace-title-icon" aria-hidden="true">
                <i class="ki-duotone ki-category fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </span>
            <span>{{ __('menu.sector_navigation.title', ['sector' => __($sectorTitle)]) }}</span>
        </div>

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
                        <a class="sector-workspace-tab {{ $tab['is_active'] ? 'active' : '' }}"
                            href="{{ $tab['url'] }}"
                            @if ($tab['is_active']) aria-current="page" @endif>
                            <i class="ki-duotone {{ $tab['icon'] }} fs-3" aria-hidden="true">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <span>{{ __($tab['title']) }}</span>
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
