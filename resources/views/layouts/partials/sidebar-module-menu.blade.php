@if($currentSidebarModule)
@php($currentNavigationGroup = null)
@foreach($currentSidebarModule['sections'] as $menu)
	@if(($menu['navigation_group'] ?? null) !== $currentNavigationGroup)
		@if($menu['navigation_group'] ?? null)
			<div class="phc-sidebar-navigation-group">
				{{ __($menu['navigation_group']) }}
			</div>
		@endif
		@php($currentNavigationGroup = $menu['navigation_group'] ?? null)
	@endif

	@if($menu['is_direct'] ?? false)
		<div
			class="menu-item phc-sidebar-section {{ ($menu['variant'] ?? null) === 'hud' ? 'phc-sidebar-hud' : '' }} {{ $menu['is_active'] ? 'phc-sidebar-section-active' : '' }}">
			<a class="menu-link" href="{{ url($menu['url']) }}">
				@if(($menu['variant'] ?? null) === 'hud')
					<span class="menu-title">{{ __($menu['title']) }}</span>
					<span class="phc-sidebar-hud-live-dot" aria-hidden="true"></span>
				@else
					<span class="menu-icon">
						<span class="phc-sidebar-icon">
							<i class="ki-duotone {{ $menu['icon'] }} fs-2">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
						</span>
					</span>

					<span class="menu-title">{{ __($menu['title']) }}</span>
				@endif
			</a>
		</div>
	@else
		<div data-kt-menu-trigger="click"
			class="menu-item menu-accordion phc-sidebar-section {{ $menu['is_active'] ? 'show phc-sidebar-section-active' : '' }}">

			<span class="menu-link">
				<span class="menu-icon">
					<span class="phc-sidebar-icon">
						<i class="ki-duotone {{ $menu['icon'] }} fs-2">
							<span class="path1"></span>
							<span class="path2"></span>
						</i>
					</span>
				</span>

				<span class="menu-title">{{ __($menu['title']) }}</span>
				<span class="phc-sidebar-item-count">{{ $menu['visible_item_count'] }}</span>
				<span class="menu-arrow"></span>
			</span>

			<div class="menu-sub menu-sub-accordion">
				@foreach($menu['items'] as $item)
					@if(isset($item['children']))
						<div class="menu-item phc-sidebar-group">
							<div class="phc-sidebar-group-label">{{ __($item['title']) }}</div>

							@foreach($item['children'] as $child)
								<div class="menu-item">
									<a class="menu-link phc-sidebar-link {{ request()->is($child['pattern']) ? 'active' : '' }}"
										href="{{ url($child['url']) }}">
										<span class="menu-bullet">
											<span class="bullet bullet-dot"></span>
										</span>
										<span class="menu-title">{{ __($child['title']) }}</span>
									</a>
								</div>
							@endforeach
						</div>
					@else
						<div class="menu-item">
							<a class="menu-link phc-sidebar-link {{ request()->is($item['pattern']) ? 'active' : '' }}"
								href="{{ url($item['url']) }}">
								<span class="menu-bullet">
									<span class="bullet bullet-dot"></span>
								</span>
								<span class="menu-title">{{ __($item['title']) }}</span>
							</a>
						</div>
					@endif
				@endforeach
			</div>
		</div>
	@endif
@endforeach
@endif
