@php
    $sidebarModules = $sidebarModules ?? collect();
    $iconButtonClass = 'kt-btn kt-btn-ghost kt-btn-icon rounded-full size-10 border border-transparent text-secondary-foreground hover:bg-background hover:[&_i]:text-primary hover:border-input [.active&]:bg-background [.active&]:[&_i]:text-primary [.active&]:border-input';
@endphp

@if($sidebarModules->isEmpty())
    <a class="{{ $iconButtonClass }}" data-kt-tooltip="" data-kt-tooltip-placement="right" href="{{ route('login') }}">
        <span class="kt-menu-icon">
            <i class="ki-filled ki-user text-lg"></i>
        </span>
        <span class="kt-tooltip" data-kt-tooltip-content="true">
            {{ __('Login') }}
        </span>
    </a>
@else
    <div class="kt-menu flex flex-col items-center gap-2.5" data-kt-menu="true">
        @foreach($sidebarModules as $sidebarModule)
            @foreach($sidebarModule['sections'] as $menu)
                @if($menu['is_direct'] ?? false)
                    <a class="{{ $iconButtonClass }} {{ $menu['is_active'] ? 'active' : '' }}"
                        data-kt-tooltip="" data-kt-tooltip-placement="right"
                        href="{{ url($menu['url']) }}">
                        <span class="kt-menu-icon">
                            <i class="ki-filled {{ $menu['icon'] }} text-lg"></i>
                        </span>
                        <span class="kt-tooltip" data-kt-tooltip-content="true">
                            {{ __($menu['title']) }}
                        </span>
                    </a>
                @else
                    <div class="kt-menu-item"
                        data-kt-menu-item-offset="0, 10px"
                        data-kt-menu-item-placement="right-start"
                        data-kt-menu-item-placement-rtl="left-start"
                        data-kt-menu-item-toggle="dropdown"
                        data-kt-menu-item-trigger="click|lg:hover">
                        <button class="kt-menu-toggle {{ $iconButtonClass }} {{ $menu['is_active'] ? 'active' : '' }}"
                            type="button">
                            <span class="kt-menu-icon">
                                <i class="ki-filled {{ $menu['icon'] }} text-lg"></i>
                            </span>
                        </button>
                        <div class="kt-menu-dropdown kt-menu-default phc-m9-sidebar-dropdown py-2 min-w-[280px] max-w-[360px]">
                            <div class="px-3 py-2 border-b border-border mb-1">
                                <div class="text-sm font-semibold text-mono">
                                    {{ __($menu['title']) }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ __($sidebarModule['title']) }}
                                </div>
                            </div>

                            @foreach($menu['items'] as $item)
                                @if(isset($item['children']))
                                    <div class="px-3 pt-2 pb-1 text-xs font-semibold text-muted-foreground">
                                        {{ __($item['title']) }}
                                    </div>
                                    @foreach($item['children'] as $child)
                                        <div class="kt-menu-item">
                                            <a class="kt-menu-link {{ request()->is($child['pattern']) ? 'active' : '' }}"
                                                href="{{ url($child['url']) }}" tabindex="0">
                                                <span class="kt-menu-bullet">
                                                    <span class="kt-menu-bullet-dot"></span>
                                                </span>
                                                <span class="kt-menu-title">
                                                    {{ __($child['title']) }}
                                                </span>
                                            </a>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="kt-menu-item">
                                        <a class="kt-menu-link {{ request()->is($item['pattern']) ? 'active' : '' }}"
                                            href="{{ url($item['url']) }}" tabindex="0">
                                            <span class="kt-menu-bullet">
                                                <span class="kt-menu-bullet-dot"></span>
                                            </span>
                                            <span class="kt-menu-title">
                                                {{ __($item['title']) }}
                                            </span>
                                        </a>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        @endforeach
    </div>
@endif
