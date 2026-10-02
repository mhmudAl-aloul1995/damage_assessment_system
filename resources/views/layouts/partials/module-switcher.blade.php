@if($currentSidebarModule)
    <div class="phc-module-switcher dropdown" id="phc_module_switcher">
        <div class="phc-module-eyebrow">{{ __('menu.module_switcher.workspace') }}</div>
        <button type="button" class="phc-module-trigger" id="phc_module_trigger"
            @if($sidebarModules->count() > 1)
                data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false"
                aria-controls="phc_module_options" aria-label="{{ __('menu.module_switcher.switch') }}: {{ __($currentSidebarModule['short_title'] ?? $currentSidebarModule['title']) }}"
            @else
                disabled
            @endif
            title="{{ __($currentSidebarModule['title']) }}">
            <span class="phc-module-icon" aria-hidden="true">
                <i class="ki-duotone {{ $currentSidebarModule['icon'] ?? 'ki-element-11' }} fs-2"><span class="path1"></span><span class="path2"></span></i>
            </span>
            <span class="phc-module-name">{{ __($currentSidebarModule['short_title'] ?? $currentSidebarModule['title']) }}</span>
            @if($sidebarModules->count() > 1)
                <svg class="phc-module-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 10 5 5 5-5" /></svg>
            @endif
        </button>
        @if($sidebarModules->count() > 1)
            <div class="dropdown-menu phc-module-options" id="phc_module_options" aria-labelledby="phc_module_trigger">
                <div class="phc-module-panel-header">
                    <strong>{{ __('menu.module_switcher.choose') }}</strong>
                    <span>{{ __('menu.module_switcher.hint') }}</span>
                </div>
                @foreach([false, true] as $central)
                    @php($moduleGroup = $sidebarModules->filter(fn (array $module): bool => (bool) ($module['central'] ?? false) === $central))
                    @if($moduleGroup->isNotEmpty())
                        <div class="phc-module-group-label">{{ __($central ? 'menu.module_switcher.administration' : 'menu.module_switcher.work_modules') }}</div>
                        @foreach($moduleGroup as $moduleOption)
                            @php($isCurrentModule = $moduleOption['key'] === $currentSidebarModule['key'])
                            <a href="{{ url($moduleOption['url']) }}" data-module-option="{{ $moduleOption['key'] }}"
                                class="dropdown-item phc-module-option {{ $isCurrentModule ? 'phc-module-option-current' : '' }}"
                                @if($isCurrentModule) aria-current="true" @endif>
                                <span class="phc-module-option-icon" aria-hidden="true">
                                    <i class="ki-duotone {{ $moduleOption['icon'] ?? 'ki-element-11' }} fs-2"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                                <span class="phc-module-option-copy">
                                    <strong>{{ __($moduleOption['short_title'] ?? $moduleOption['title']) }}</strong>
                                    @if(isset($moduleOption['description']))
                                        <span>{{ __($moduleOption['description']) }}</span>
                                    @endif
                                </span>
                                @if($isCurrentModule)
                                    <span class="phc-module-current-mark" title="{{ __('menu.module_switcher.current') }}">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                                        <span class="visually-hidden">{{ __('menu.module_switcher.current') }}</span>
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    @endif
                @endforeach
                <div class="phc-module-panel-footer">{{ __('menu.module_switcher.available') }}</div>
            </div>
        @endif
    </div>
@endif
