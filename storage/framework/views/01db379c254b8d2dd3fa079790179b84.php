<?php
	$isRtl = app()->getLocale() === 'ar';
	$direction = $isRtl ? 'rtl' : 'ltr';
	$suffix = $isRtl ? '.rtl' : '';
	$quickSearchLinks = [
		[
			'title' => __('ui.search.dashboard'),
			'subtitle' => __('ui.damage_dashboard.title'),
			'url' => url('damageAssessment'),
			'icon' => 'ki-element-11',
		],
		[
			'title' => __('ui.search.buildings'),
			'subtitle' => __('ui.damage_dashboard.buildings'),
			'url' => url('building'),
			'icon' => 'ki-home',
		],
		[
			'title' => __('ui.search.housing_units'),
			'subtitle' => __('ui.damage_dashboard.housing_units'),
			'url' => url('housing'),
			'icon' => 'ki-home-2',
		],
		[
			'title' => __('ui.search.public_buildings'),
			'subtitle' => __('ui.damage_dashboard.public_buildings'),
			'url' => route('public-buildings.index'),
			'icon' => 'ki-office-bag',
		],
		[
			'title' => __('ui.search.road_facilities'),
			'subtitle' => __('ui.damage_dashboard.road_facilities'),
			'url' => route('road-facilities.index'),
			'icon' => 'ki-map',
		],
		[
			'title' => __('ui.search.audit_dashboard'),
			'subtitle' => __('ui.audit_dashboard.title'),
			'url' => route('audit.dashboard'),
			'icon' => 'ki-shield-tick',
		],
		[
			'title' => __('ui.search.exports'),
			'subtitle' => __('ui.exports.title'),
			'url' => route('export.data.index'),
			'icon' => 'ki-file-down',
		],
	];
?>
<!DOCTYPE html>

<html lang="<?php echo e(app()->getLocale()); ?>" direction="<?php echo e($direction); ?>" dir="<?php echo e($direction); ?>"
	style="direction: <?php echo e($direction); ?>">
<!--begin::Head-->

<head>
	<base href="" />
	<title><?php echo e(__('ui.app.name')); ?></title>
	<meta charset="utf-8" />
	<meta name="description" content="" />
	<meta name="keywords" content="" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
	<meta property="og:locale" content="<?php echo e($isRtl ? 'ar_PS' : 'en_US'); ?>" />
	<meta property="og:type" content="article" />
	<meta property="og:title" content="" />
	<meta property="og:url" content="https://keenthemes.com/metronic" />
	<meta property="og:site_name" content="Keenthemes | Metronic" />
	<link rel="canonical" href="https://preview.keenthemes.com/metronic8" />
	<link rel="shortcut icon" href="<?php echo e(asset('assets/media/logos/logo_641.png')); ?>" />
	<!--begin::Fonts(mandatory for all pages)-->
	<link rel="stylesheet" href="<?php echo e(asset('assets/css/fontface.css')); ?>">
	<!--end::Fonts-->
	<!--begin::Vendor Stylesheets(used for this page only)-->
	<link href="<?php echo e(url('')); ?>/assets/plugins/custom/fullcalendar/fullcalendar.bundle<?php echo e($suffix); ?>.css" rel="stylesheet"
		type="text/css" />
	<link href="<?php echo e(url('')); ?>/assets/plugins/custom/datatables/datatables.bundle<?php echo e($suffix); ?>.css" rel="stylesheet"
		type="text/css" />
	<!--end::Vendor Stylesheets-->
	<!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
	<link href="<?php echo e(url('')); ?>/assets/plugins/global/plugins.bundle<?php echo e($suffix); ?>.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo e(url('')); ?>/assets/css/style.bundle<?php echo e($suffix); ?>.css" rel="stylesheet" type="text/css" />
	<link href="<?php echo e(asset('assets/css/font-unified.css')); ?>" rel="stylesheet" type="text/css" />
	<!--end::Global Stylesheets Bundle-->
	<script>// Frame-busting to prevent site from being loaded within a frame without permission (click-jacking) if (window.top != window.self) { window.top.location.replace(window.self.location.href); }</script>
</head>
<!--end::Head-->
<!--begin::Body-->

<style>
	@font-face {
		font-family: 'Droid Arabic Kufi';
		src: url('<?php echo e(asset("assets/DroidArabicKufi.eot")); ?>');
		src:
			url('<?php echo e(asset("assets/DroidArabicKufi.eot?#iefix")); ?>') format('embedded-opentype'),
			url('<?php echo e(asset("assets/DroidArabicKufi.woff")); ?>') format('woff'),
			url('<?php echo e(asset("assets/DroidArabicKufi.ttf")); ?>') format('truetype');

		font-weight: normal;
		font-style: normal;
	}

	[type="tel"],
	[type="url"],
	[type="email"],
	[type="number"],
	table {
		direction:
			<?php echo e($direction); ?>

			!important;
	}

	body.locale-rtl .form-check {
		padding-right: 1.5rem;
		padding-left: 0;
	}

	body.locale-rtl .form-check .form-check-input {
		float: right;
		margin-right: -1.5rem;
		margin-left: 0;
	}
</style>

<body id="kt_app_body" data-kt-app-layout="dark-sidebar" data-kt-app-header-fixed="true"
	data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true"
	data-kt-app-sidebar-push-header="true" data-kt-app-sidebar-push-toolbar="true"
	data-kt-app-sidebar-push-footer="true" data-kt-app-toolbar-enabled="true"
	class="app-default locale-<?php echo e($direction); ?>" data-locale="<?php echo e(app()->getLocale()); ?>" data-direction="<?php echo e($direction); ?>">
	<!--begin::Theme mode setup on page load-->
	<script>var defaultThemeMode = "light"; var themeMode; if (document.documentElement) { if (document.documentElement.hasAttribute("data-bs-theme-mode")) { themeMode = document.documentElement.getAttribute("data-bs-theme-mode"); } else { if (localStorage.getItem("data-bs-theme") !== null) { themeMode = localStorage.getItem("data-bs-theme"); } else { themeMode = defaultThemeMode; } } if (themeMode === "system") { themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"; } document.documentElement.setAttribute("data-bs-theme", themeMode); }</script>
	<!--end::Theme mode setup on page load-->
	<!--begin::App-->
	<div class="d-flex flex-column flex-root app-root" id="kt_app_root">
		<!--begin::Page-->
		<div class="app-page flex-column flex-column-fluid" id="kt_app_page">
			<!--begin::Header-->
			<div id="kt_app_header" class="app-header" data-kt-sticky="true"
				data-kt-sticky-activate="{default: true, lg: true}" data-kt-sticky-name="app-header-minimize"
				data-kt-sticky-offset="{default: '200px', lg: '0'}" data-kt-sticky-animation="false">
				<!--begin::Header container-->
				<div class="app-container container-fluid d-flex align-items-stretch justify-content-between"
					id="kt_app_header_container">
					<!--begin::Sidebar mobile toggle-->
					<div class="d-flex align-items-center d-lg-none ms-n3 me-1 me-md-2"
						title="<?php echo e(__('ui.search.show_sidebar')); ?>">
						<div class="btn btn-icon btn-active-color-primary w-35px h-35px"
							id="kt_app_sidebar_mobile_toggle">
							<i class="ki-duotone ki-abstract-14 fs-2 fs-md-1">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
						</div>
					</div>
					<!--end::Sidebar mobile toggle-->
					<!--begin::Mobile logo-->
					<div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
						<a href="../../demo1/dist/index.html" class="d-lg-none">
							<img alt="Logo" src="<?php echo e(url('')); ?>/assets/media/logos/default-small.svg" class="h-30px" />
						</a>
					</div>
					<!--end::Mobile logo-->
					<!--begin::Header wrapper-->
					<div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1"
						id="kt_app_header_wrapper">
						<!--begin::Menu wrapper-->
						<!--<div class="app-header-menu app-header-mobile-drawer align-items-stretch" data-kt-drawer="true" data-kt-drawer-name="app-header-menu" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="250px" data-kt-drawer-direction="end" data-kt-drawer-toggle="#kt_app_header_menu_toggle" data-kt-swapper="true" data-kt-swapper-mode="{default: 'append', lg: 'prepend'}" data-kt-swapper-parent="{default: '#kt_app_body', lg: '#kt_app_header_wrapper'}">
								<!--begin::Menu-->
						<div class="menu menu-rounded menu-column menu-lg-row my-5 my-lg-0 align-items-stretch fw-semibold px-2 px-lg-0"
							id="kt_app_header_menu" data-kt-menu="true">



						</div>
						<!--end::Menu-->
					</div>
					<!--end::Menu wrapper-->
					<!--begin::Navbar-->
					<div class="app-navbar flex-shrink-0">
						<!--begin::Search-->
						<div class="app-navbar-item align-items-stretch ms-1 ms-md-4">
							<!--begin::Search-->
							<div id="kt_header_search" class="header-search d-flex align-items-stretch"
								data-kt-search-keypress="true" data-kt-search-min-length="2"
								data-kt-search-enter="enter" data-kt-search-layout="menu" data-kt-menu-trigger="auto"
								data-kt-menu-overflow="false" data-kt-menu-permanent="true"
								data-kt-menu-placement="bottom-end">
								<!--begin::Search toggle-->
								<div class="d-flex align-items-center" data-kt-search-element="toggle"
									id="kt_header_search_toggle">
									<div
										class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px">
										<i class="ki-duotone ki-magnifier fs-2">
											<span class="path1"></span>
											<span class="path2"></span>
										</i>
									</div>
								</div>
								<!--end::Search toggle-->
								<!--begin::Menu-->
								<div data-kt-search-element="content"
									class="menu menu-sub menu-sub-dropdown p-7 w-325px w-md-375px">
									<!--begin::Wrapper-->
									<div data-kt-search-element="wrapper">
										<!--begin::Form-->
										<form data-kt-search-element="form" class="w-100 position-relative mb-3"
											autocomplete="off">
											<!--begin::Icon-->
											<i
												class="ki-duotone ki-magnifier fs-2 text-gray-500 position-absolute top-50 translate-middle-y ms-0">
												<span class="path1"></span>
												<span class="path2"></span>
											</i>
											<!--end::Icon-->
											<!--begin::Input-->
											<input type="text" id="global-search-input"
												class="search-input form-control form-control-flush ps-10" name="search"
												value="" placeholder="<?php echo e(__('ui.search.placeholder')); ?>"
												data-kt-search-element="input" />
											<!--end::Input-->
											<!--begin::Spinner-->
											<span
												class="search-spinner position-absolute top-50 end-0 translate-middle-y lh-0 d-none me-1"
												data-kt-search-element="spinner">
												<span
													class="spinner-border h-15px w-15px align-middle text-gray-400"></span>
											</span>
											<!--end::Spinner-->
											<!--begin::Reset-->
											<span
												class="search-reset btn btn-flush btn-active-color-primary position-absolute top-50 end-0 translate-middle-y lh-0 d-none"
												data-kt-search-element="clear">
												<i class="ki-duotone ki-cross fs-2 fs-lg-1 me-0">
													<span class="path1"></span>
													<span class="path2"></span>
												</i>
											</span>
											<!--end::Reset-->
											<!--begin::Toolbar-->
											<div class="position-absolute top-50 end-0 translate-middle-y"
												data-kt-search-element="toolbar">
												<!--begin::Preferences toggle-->
												<div data-kt-search-element="preferences-show"
													class="btn btn-icon w-20px btn-sm btn-active-color-primary me-1"
													data-bs-toggle="tooltip" title="<?php echo e(__('ui.search.preferences')); ?>">
													<i class="ki-duotone ki-setting-2 fs-2">
														<span class="path1"></span>
														<span class="path2"></span>
													</i>
												</div>
												<!--end::Preferences toggle-->
												<!--begin::Advanced search toggle-->
												<div data-kt-search-element="advanced-options-form-show"
													class="btn btn-icon w-20px btn-sm btn-active-color-primary"
													data-bs-toggle="tooltip" title="<?php echo e(__('ui.search.advanced')); ?>">
													<i class="ki-duotone ki-down fs-2"></i>
												</div>
												<!--end::Advanced search toggle-->
											</div>
											<!--end::Toolbar-->
										</form>
										<!--end::Form-->
										<!--begin::Separator-->
										<div class="separator border-gray-200 mb-6"></div>
										<!--end::Separator-->
										<!--begin::Recently viewed-->
										<div data-kt-search-element="results" class="d-none">
											<!--begin::Items-->
											<div class="scroll-y mh-200px mh-lg-350px">
												<!--begin::Category title-->
												<h3 class="fs-5 text-muted m-0 pb-5"
													data-kt-search-element="category-title"><?php echo e(__('ui.search.users')); ?>

												</h3>
												<!--end::Category title-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<img src="<?php echo e(url('')); ?>/assets/media/avatars/300-6.jpg" alt="" />
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column justify-content-start fw-semibold">
														<span class="fs-6 fw-semibold">Karina Clark</span>
														<span class="fs-7 fw-semibold text-muted">Marketing
															Manager</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<img src="<?php echo e(url('')); ?>/assets/media/avatars/300-2.jpg" alt="" />
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column justify-content-start fw-semibold">
														<span class="fs-6 fw-semibold">Olivia Bold</span>
														<span class="fs-7 fw-semibold text-muted">Software
															Engineer</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<img src="<?php echo e(url('')); ?>/assets/media/avatars/300-9.jpg" alt="" />
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column justify-content-start fw-semibold">
														<span class="fs-6 fw-semibold">Ana Clark</span>
														<span class="fs-7 fw-semibold text-muted">UI/UX Designer</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<img src="<?php echo e(url('')); ?>/assets/media/avatars/300-14.jpg" alt="" />
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column justify-content-start fw-semibold">
														<span class="fs-6 fw-semibold">Nick Pitola</span>
														<span class="fs-7 fw-semibold text-muted">Art Director</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<img src="<?php echo e(url('')); ?>/assets/media/avatars/300-11.jpg" alt="" />
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column justify-content-start fw-semibold">
														<span class="fs-6 fw-semibold">Edward Kulnic</span>
														<span class="fs-7 fw-semibold text-muted">System
															Database Officer</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Category title-->
												<h3 class="fs-5 text-muted m-0 pt-5 pb-5"
													data-kt-search-element="category-title">
													<?php echo e(__('ui.search.customers')); ?></h3>
												<!--end::Category title-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<img class="w-20px h-20px"
																src="<?php echo e(url('')); ?>/assets/media/svg/brand-logos/volicity-9.svg"
																alt="" />
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column justify-content-start fw-semibold">
														<span class="fs-6 fw-semibold">Company Rbranding</span>
														<span class="fs-7 fw-semibold text-muted">UI Design</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<img class="w-20px h-20px"
																src="<?php echo e(url('')); ?>/assets/media/svg/brand-logos/tvit.svg"
																alt="" />
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column justify-content-start fw-semibold">
														<span class="fs-6 fw-semibold">Company Re-branding</span>
														<span class="fs-7 fw-semibold text-muted">Web Development</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<img class="w-20px h-20px"
																src="<?php echo e(url('')); ?>/assets/media/svg/misc/infography.svg"
																alt="" />
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column justify-content-start fw-semibold">
														<span class="fs-6 fw-semibold">Business Analytics App</span>
														<span class="fs-7 fw-semibold text-muted">Administration</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<img class="w-20px h-20px"
																src="<?php echo e(url('')); ?>/assets/media/svg/brand-logos/leaf.svg"
																alt="" />
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column justify-content-start fw-semibold">
														<span class="fs-6 fw-semibold">EcoLeaf App Launch</span>
														<span class="fs-7 fw-semibold text-muted">Marketing</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<img class="w-20px h-20px"
																src="<?php echo e(url('')); ?>/assets/media/svg/brand-logos/tower.svg"
																alt="" />
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column justify-content-start fw-semibold">
														<span class="fs-6 fw-semibold">Tower Group Website</span>
														<span class="fs-7 fw-semibold text-muted">Google Adwords</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Category title-->
												<h3 class="fs-5 text-muted m-0 pt-5 pb-5"
													data-kt-search-element="category-title">
													<?php echo e(__('ui.search.projects')); ?></h3>
												<!--end::Category title-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-notepad fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
																<span class="path3"></span>
																<span class="path4"></span>
																<span class="path5"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<span class="fs-6 fw-semibold">Si-Fi Project by AU Themes</span>
														<span class="fs-7 fw-semibold text-muted">#45670</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-frame fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
																<span class="path3"></span>
																<span class="path4"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<span class="fs-6 fw-semibold">Shopix Mobile App Planning</span>
														<span class="fs-7 fw-semibold text-muted">#45690</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-message-text-2 fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
																<span class="path3"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<span class="fs-6 fw-semibold">Finance Monitoring SAAS
															Discussion</span>
														<span class="fs-7 fw-semibold text-muted">#21090</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
												<!--begin::Item-->
												<a href="#"
													class="d-flex text-dark text-hover-primary align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-profile-circle fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
																<span class="path3"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<span class="fs-6 fw-semibold">Dashboard Analitics Launch</span>
														<span class="fs-7 fw-semibold text-muted">#34560</span>
													</div>
													<!--end::Title-->
												</a>
												<!--end::Item-->
											</div>
											<!--end::Items-->
										</div>
										<!--end::Recently viewed-->
										<!--begin::Recently viewed-->
										<div class="mb-5" data-kt-search-element="main">
											<!--begin::Heading-->
											<div class="d-flex flex-stack fw-semibold mb-4">
												<!--begin::Label-->
												<span class="text-muted fs-6 me-2">Recently Searched:</span>
												<!--end::Label-->
											</div>
											<!--end::Heading-->
											<!--begin::Items-->
											<div class="scroll-y mh-200px mh-lg-325px">
												<!--begin::Item-->
												<div class="d-flex align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-laptop fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<a href="#"
															class="fs-6 text-gray-800 text-hover-primary fw-semibold">BoomApp
															by Keenthemes</a>
														<span class="fs-7 text-muted fw-semibold">#45789</span>
													</div>
													<!--end::Title-->
												</div>
												<!--end::Item-->
												<!--begin::Item-->
												<div class="d-flex align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-chart-simple fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
																<span class="path3"></span>
																<span class="path4"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<a href="#"
															class="fs-6 text-gray-800 text-hover-primary fw-semibold">"Kept
															API Project Meeting</a>
														<span class="fs-7 text-muted fw-semibold">#84050</span>
													</div>
													<!--end::Title-->
												</div>
												<!--end::Item-->
												<!--begin::Item-->
												<div class="d-flex align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-chart fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<a href="#"
															class="fs-6 text-gray-800 text-hover-primary fw-semibold">"KPI
															Monitoring App Launch</a>
														<span class="fs-7 text-muted fw-semibold">#84250</span>
													</div>
													<!--end::Title-->
												</div>
												<!--end::Item-->
												<!--begin::Item-->
												<div class="d-flex align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-chart-line-down fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<a href="#"
															class="fs-6 text-gray-800 text-hover-primary fw-semibold">Project
															Reference FAQ</a>
														<span class="fs-7 text-muted fw-semibold">#67945</span>
													</div>
													<!--end::Title-->
												</div>
												<!--end::Item-->
												<!--begin::Item-->
												<div class="d-flex align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-sms fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<a href="#"
															class="fs-6 text-gray-800 text-hover-primary fw-semibold">"FitPro
															App Development</a>
														<span class="fs-7 text-muted fw-semibold">#84250</span>
													</div>
													<!--end::Title-->
												</div>
												<!--end::Item-->
												<!--begin::Item-->
												<div class="d-flex align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-bank fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<a href="#"
															class="fs-6 text-gray-800 text-hover-primary fw-semibold">Shopix
															Mobile App</a>
														<span class="fs-7 text-muted fw-semibold">#45690</span>
													</div>
													<!--end::Title-->
												</div>
												<!--end::Item-->
												<!--begin::Item-->
												<div class="d-flex align-items-center mb-5">
													<!--begin::Symbol-->
													<div class="symbol symbol-40px me-4">
														<span class="symbol-label bg-light">
															<i class="ki-duotone ki-chart-line-down fs-2 text-primary">
																<span class="path1"></span>
																<span class="path2"></span>
															</i>
														</span>
													</div>
													<!--end::Symbol-->
													<!--begin::Title-->
													<div class="d-flex flex-column">
														<a href="#"
															class="fs-6 text-gray-800 text-hover-primary fw-semibold">"Landing
															UI Design" Launch</a>
														<span class="fs-7 text-muted fw-semibold">#24005</span>
													</div>
													<!--end::Title-->
												</div>
												<!--end::Item-->
											</div>
											<!--end::Items-->
										</div>
										<!--end::Recently viewed-->
										<!--begin::Empty-->
										<div data-kt-search-element="empty" class="text-center d-none">
											<!--begin::Icon-->
											<div class="pt-10 pb-10">
												<i class="ki-duotone ki-search-list fs-4x opacity-50">
													<span class="path1"></span>
													<span class="path2"></span>
													<span class="path3"></span>
												</i>
											</div>
											<!--end::Icon-->
											<!--begin::Message-->
											<div class="pb-15 fw-semibold">
												<h3 class="text-gray-600 fs-5 mb-2">No result found</h3>
												<div class="text-muted fs-7">Please try again with a different query
												</div>
											</div>
											<!--end::Message-->
										</div>
										<!--end::Empty-->
									</div>
									<!--end::Wrapper-->
									<!--begin::Preferences-->
									<form data-kt-search-element="advanced-options-form" class="pt-1 d-none">
										<!--begin::Heading-->
										<h3 class="fw-semibold text-dark mb-7">Advanced Search</h3>
										<!--end::Heading-->
										<!--begin::Input group-->
										<div class="mb-5">
											<input type="text" class="form-control form-control-sm form-control-solid"
												placeholder="Contains the word" name="query" />
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="mb-5">
											<!--begin::Radio group-->
											<div class="nav-group nav-group-fluid">
												<!--begin::Option-->
												<label>
													<input type="radio" class="btn-check" name="type" value="has"
														checked="checked" />
													<span
														class="btn btn-sm btn-color-muted btn-active btn-active-primary">All</span>
												</label>
												<!--end::Option-->
												<!--begin::Option-->
												<label>
													<input type="radio" class="btn-check" name="type" value="users" />
													<span
														class="btn btn-sm btn-color-muted btn-active btn-active-primary px-4"><?php echo e(__('ui.search.users')); ?></span>
												</label>
												<!--end::Option-->
												<!--begin::Option-->
												<label>
													<input type="radio" class="btn-check" name="type" value="orders" />
													<span
														class="btn btn-sm btn-color-muted btn-active btn-active-primary px-4">Orders</span>
												</label>
												<!--end::Option-->
												<!--begin::Option-->
												<label>
													<input type="radio" class="btn-check" name="type"
														value="projects" />
													<span
														class="btn btn-sm btn-color-muted btn-active btn-active-primary px-4"><?php echo e(__('ui.search.projects')); ?></span>
												</label>
												<!--end::Option-->
											</div>
											<!--end::Radio group-->
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="mb-5">
											<input type="text" name="assignedto"
												class="form-control form-control-sm form-control-solid"
												placeholder="Assigned to" value="" />
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="mb-5">
											<input type="text" name="collaborators"
												class="form-control form-control-sm form-control-solid"
												placeholder="Collaborators" value="" />
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="mb-5">
											<!--begin::Radio group-->
											<div class="nav-group nav-group-fluid">
												<!--begin::Option-->
												<label>
													<input type="radio" class="btn-check" name="attachment" value="has"
														checked="checked" />
													<span
														class="btn btn-sm btn-color-muted btn-active btn-active-primary">Has
														attachment</span>
												</label>
												<!--end::Option-->
												<!--begin::Option-->
												<label>
													<input type="radio" class="btn-check" name="attachment"
														value="any" />
													<span
														class="btn btn-sm btn-color-muted btn-active btn-active-primary px-4">Any</span>
												</label>
												<!--end::Option-->
											</div>
											<!--end::Radio group-->
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="mb-5">
											<select name="timezone" aria-label="Select a Timezone"
												data-control="select2" data-dropdown-parent="#kt_header_search"
												data-placeholder="date_period"
												class="form-select form-select-sm form-select-solid">
												<option value="next">Within the next</option>
												<option value="last">Within the last</option>
												<option value="between">Between</option>
												<option value="on">On</option>
											</select>
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="row mb-8">
											<!--begin::Col-->
											<div class="col-6">
												<input type="number" name="date_number"
													class="form-control form-control-sm form-control-solid"
													placeholder="Lenght" value="" />
											</div>
											<!--end::Col-->
											<!--begin::Col-->
											<div class="col-6">
												<select name="date_typer" aria-label="Select a Timezone"
													data-control="select2" data-dropdown-parent="#kt_header_search"
													data-placeholder="Period"
													class="form-select form-select-sm form-select-solid">
													<option value="days">Days</option>
													<option value="weeks">Weeks</option>
													<option value="months">Months</option>
													<option value="years">Years</option>
												</select>
											</div>
											<!--end::Col-->
										</div>
										<!--end::Input group-->
										<!--begin::Actions-->
										<div class="d-flex justify-content-end">
											<button type="reset"
												class="btn btn-sm btn-light fw-bold btn-active-light-primary me-2"
												data-kt-search-element="advanced-options-form-cancel"><?php echo e(__('ui.buttons.cancel')); ?></button>
											<a href="../../demo1/dist/pages/search/horizontal.html"
												class="btn btn-sm fw-bold btn-primary"
												data-kt-search-element="advanced-options-form-search">Search</a>
										</div>
										<!--end::Actions-->
									</form>
									<!--end::Preferences-->
									<!--begin::Preferences-->
									<form data-kt-search-element="preferences" class="pt-1 d-none">
										<!--begin::Heading-->
										<h3 class="fw-semibold text-dark mb-7">Search Preferences</h3>
										<!--end::Heading-->
										<!--begin::Input group-->
										<div class="pb-4 border-bottom">
											<label
												class="form-check form-switch form-switch-sm form-check-custom form-check-solid flex-stack">
												<span
													class="form-check-label text-gray-700 fs-6 fw-semibold ms-0 me-2"><?php echo e(__('ui.search.projects')); ?></span>
												<input class="form-check-input" type="checkbox" value="1"
													checked="checked" />
											</label>
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="py-4 border-bottom">
											<label
												class="form-check form-switch form-switch-sm form-check-custom form-check-solid flex-stack">
												<span
													class="form-check-label text-gray-700 fs-6 fw-semibold ms-0 me-2">Targets</span>
												<input class="form-check-input" type="checkbox" value="1"
													checked="checked" />
											</label>
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="py-4 border-bottom">
											<label
												class="form-check form-switch form-switch-sm form-check-custom form-check-solid flex-stack">
												<span
													class="form-check-label text-gray-700 fs-6 fw-semibold ms-0 me-2">Affiliate
													Programs</span>
												<input class="form-check-input" type="checkbox" value="1" />
											</label>
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="py-4 border-bottom">
											<label
												class="form-check form-switch form-switch-sm form-check-custom form-check-solid flex-stack">
												<span
													class="form-check-label text-gray-700 fs-6 fw-semibold ms-0 me-2">Referrals</span>
												<input class="form-check-input" type="checkbox" value="1"
													checked="checked" />
											</label>
										</div>
										<!--end::Input group-->
										<!--begin::Input group-->
										<div class="py-4 border-bottom">
											<label
												class="form-check form-switch form-switch-sm form-check-custom form-check-solid flex-stack">
												<span
													class="form-check-label text-gray-700 fs-6 fw-semibold ms-0 me-2"><?php echo e(__('ui.search.users')); ?></span>
												<input class="form-check-input" type="checkbox" value="1" />
											</label>
										</div>
										<!--end::Input group-->
										<!--begin::Actions-->
										<div class="d-flex justify-content-end pt-7">
											<button type="reset"
												class="btn btn-sm btn-light fw-bold btn-active-light-primary me-2"
												data-kt-search-element="preferences-dismiss"><?php echo e(__('ui.buttons.cancel')); ?></button>
											<button type="submit"
												class="btn btn-sm fw-bold btn-primary"><?php echo e(__('ui.buttons.save')); ?></button>
										</div>
										<!--end::Actions-->
									</form>
									<!--end::Preferences-->
								</div>
								<!--end::Menu-->
							</div>
							<!--end::Search-->
						</div>

						<div class="app-navbar-item ms-1 ms-md-4">
							<div class="btn-group" role="group" aria-label="<?php echo e(__('ui.locale.switcher')); ?>">
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = config('app.supported_locales', ['en']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $locale): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
									<form method="POST" action="<?php echo e(route('locale.update', $locale)); ?>" class="locale-switcher-form" data-locale="<?php echo e($locale); ?>">
										<?php echo csrf_field(); ?>
										<button type="submit"
											class="btn btn-sm <?php echo e(app()->getLocale() === $locale ? 'btn-primary' : 'btn-light'); ?>">
											<?php echo e(__('ui.locale.' . ($locale === 'ar' ? 'arabic' : 'english'))); ?>

										</button>
									</form>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
							</div>
						</div>

						<!--end::My apps links-->
						<!--begin::User menu-->
						<div class="app-navbar-item ms-1 ms-md-4" id="kt_header_user_menu_toggle">
							<!--begin::Menu wrapper-->
							<div class="cursor-pointer symbol symbol-35px"
								data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent"
								data-kt-menu-placement="bottom-end">
								<img src="<?php echo e(Auth::user()->avatar ? asset('storage/' . Auth::user()->avatar) : asset('assets/media/avatars/blank.png')); ?>"
									class="rounded-3" alt="user" />
							</div>
							<!--begin::User account menu-->
							<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-350px"
								data-kt-menu="true" dir="rtl">

								<!--begin::Menu item-->
								<div class="menu-item px-3">
									<div class="menu-content d-flex align-items-start px-3 py-4 w-100">
										<!--begin::Avatar-->
										<div class="symbol symbol-50px ms-4 flex-shrink-0">
											<img alt="Avatar"
												src="<?php echo e(Auth::user()->avatar ? asset('storage/' . Auth::user()->avatar) : asset('assets/media/avatars/blank.png')); ?>"
												class="object-fit-cover rounded" />
										</div>
										<!--end::Avatar-->

										<!--begin::User info-->
										<div class="d-flex flex-column flex-grow-1 text-end w-100"
											style="min-width: 0;">
											<div class="fw-bold fs-5 text-gray-900 mb-2"
												style="white-space: normal; word-break: break-word; overflow-wrap: anywhere; line-height: 1.6;">
												<?php echo e(Auth::user()->name); ?>

											</div>

											<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->getRoleNames()->first()): ?>
												<div class="mb-2">
													<span class="badge badge-light-success fw-bold fs-8 px-3 py-2">
														<?php echo e(Auth::user()->getRoleNames()->first()); ?>

													</span>
												</div>
											<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

											<a href="mailto:<?php echo e(Auth::user()->email); ?>"
												class="fw-semibold fs-7 text-muted text-hover-primary d-block"
												style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
												<?php echo e(Auth::user()->email); ?>

											</a>
										</div>
										<!--end::User info-->
									</div>
								</div>
								<!--end::Menu item-->

								<!--begin::Menu separator-->
								<div class="separator my-2"></div>
								<!--end::Menu separator-->

								<!--begin::Menu item-->
								<div class="menu-item px-5">
									<a href="<?php echo e(route('profile.edit')); ?>"
										class="menu-link px-5"><?php echo e(__('ui.nav.profile')); ?></a>
								</div>

								<div class="separator my-2"></div>

								<!--begin::Menu item-->
								<div class="menu-item px-5" data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
									data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">
									<a href="#" class="menu-link px-5">
										<span class="menu-title position-relative">
											<?php echo e(__('ui.theme.mode')); ?>

											<span class="ms-5 position-absolute translate-middle-y top-50 end-0">
												<i class="ki-duotone ki-night-day theme-light-show fs-2">
													<span class="path1"></span>
													<span class="path2"></span>
													<span class="path3"></span>
													<span class="path4"></span>
													<span class="path5"></span>
													<span class="path6"></span>
													<span class="path7"></span>
													<span class="path8"></span>
													<span class="path9"></span>
													<span class="path10"></span>
												</i>
												<i class="ki-duotone ki-moon theme-dark-show fs-2">
													<span class="path1"></span>
													<span class="path2"></span>
												</i>
											</span>
										</span>
									</a>

									<!--begin::Menu-->
									<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px"
										data-kt-menu="true" data-kt-element="theme-mode-menu">

										<!--begin::Menu item-->
										<div class="menu-item px-3 my-0">
											<a href="#" class="menu-link px-3 py-2" data-kt-element="mode"
												data-kt-value="light">
												<span class="menu-icon" data-kt-element="icon">
													<i class="ki-duotone ki-night-day fs-2">
														<span class="path1"></span>
														<span class="path2"></span>
														<span class="path3"></span>
														<span class="path4"></span>
														<span class="path5"></span>
														<span class="path6"></span>
														<span class="path7"></span>
														<span class="path8"></span>
														<span class="path9"></span>
														<span class="path10"></span>
													</i>
												</span>
												<span class="menu-title"><?php echo e(__('ui.theme.light')); ?></span>
											</a>
										</div>
										<!--end::Menu item-->

										<!--begin::Menu item-->
										<div class="menu-item px-3 my-0">
											<a href="#" class="menu-link px-3 py-2" data-kt-element="mode"
												data-kt-value="dark">
												<span class="menu-icon" data-kt-element="icon">
													<i class="ki-duotone ki-moon fs-2">
														<span class="path1"></span>
														<span class="path2"></span>
													</i>
												</span>
												<span class="menu-title"><?php echo e(__('ui.theme.dark')); ?></span>
											</a>
										</div>
										<!--end::Menu item-->

									</div>
									<!--end::Menu-->
								</div>
								<!--end::Menu item-->

								<!--begin::Menu item-->
								<div class="menu-item px-5">
									<a href="#" class="menu-link px-5"
										onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
										<?php echo e(__('ui.nav.logout')); ?>

									</a>
								</div>

								<!-- The hidden form -->
								<form id="logout-form" action="<?php echo e(route('logout')); ?>" method="POST"
									style="display: none;">
									<?php echo csrf_field(); ?>
								</form>
								<!--end::Menu item-->

							</div>
							<!--end::User account menu-->
							<!--end::Menu wrapper-->
						</div>
						<!--end::User menu-->
						<!--begin::Header menu toggle-->
						<div class="app-navbar-item d-lg-none ms-2 me-n2" title="<?php echo e(__('ui.search.show_header')); ?>">
							<div class="btn btn-flex btn-icon btn-active-color-primary w-30px h-30px"
								id="kt_app_header_menu_toggle">
								<i class="ki-duotone ki-element-4 fs-1">
									<span class="path1"></span>
									<span class="path2"></span>
								</i>
							</div>
						</div>
						<!--end::Header menu toggle-->
						<!--begin::Aside toggle-->
						<!--end::Header menu toggle-->
					</div>
					<!--end::Navbar-->
				</div>
				<!--end::Header wrapper-->
			</div>
			<!--end::Header container-->
		</div>
		<!--end::Header-->
		<!--begin::Wrapper-->
		<div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
			<!--begin::Sidebar-->
			<div id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true"
				data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}"
				data-kt-drawer-overlay="true" data-kt-drawer-width="225px" data-kt-drawer-direction="start"
				data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">
				<!--begin::Logo-->
				<div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
					<!--begin::Logo image-->
					<a href="<?php echo e(url('/')); ?>">
						<img style="width: 230px; height: auto;;" alt="Logo"
							src="<?php echo e(asset('assets/media/logos/LogoGaza2.jpeg')); ?>"
							class="h-65px app-sidebar-logo-default" />
						<style>
							.app-sidebar-minimize {
								max-width: 58px;
							}
						</style>
						<!-- Minimized Logo (visible when sidebar is closed) -->
						<!-- Pro-tip: Use a small square icon here -->
						<img alt="Logo" src="<?php echo e(asset('assets/media/logos/logo_64.png')); ?>"
							class="h-40px app-sidebar-minimize app-sidebar-logo-minimize" />

					</a>
					<!--end::Logo image-->
					<!--begin::Sidebar toggle-->
					<!--begin::Minimized sidebar setup:
            if (isset($_COOKIE["sidebar_minimize_state"]) && $_COOKIE["sidebar_minimize_state"] === "on") {
                1. "src/js/layout/sidebar.js" adds "sidebar_minimize_state" cookie value to save the sidebar minimize state.
                2. Set data-kt-app-sidebar-minimize="on" attribute for body tag.
                3. Set data-kt-toggle-state="active" attribute to the toggle element with "kt_app_sidebar_toggle" id.
                4. Add "active" class to to sidebar toggle element with "kt_app_sidebar_toggle" id.
            }
        -->
					<div id="kt_app_sidebar_toggle"
						class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm btn-color-muted btn-active-color-primary h-30px w-30px position-absolute top-50 start-100 translate-middle rotate"
						data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
						data-kt-toggle-name="app-sidebar-minimize">
						<i class="ki-duotone ki-black-left-line fs-3 rotate-180">
							<span class="path1"></span>
							<span class="path2"></span>
						</i>
					</div>
					<!--end::Sidebar toggle-->
				</div>
				<!--end::Logo-->
				<!--begin::sidebar menu-->
				<div class="app-sidebar-menu overflow-hidden flex-column-fluid">
					<div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper">

						<div id="kt_app_sidebar_menu_scroll" class="scroll-y my-5 mx-3" data-kt-scroll="true"
							data-kt-scroll-activate="true" data-kt-scroll-height="auto"
							data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer"
							data-kt-scroll-offset="5px" data-kt-scroll-save-state="true">

							<div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6"
								id="kt_app_sidebar_menu" data-kt-menu="true" data-kt-menu-expand="false">

								<?php
									$user = auth()->user();
								?>

								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = config('sidebar'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $menu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>

									
									<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->hasAnyRole($menu['roles'] ?? [])): ?>

										<?php
											$visibleItems = collect($menu['items'] ?? [])->filter(function ($item) use ($user) {
												return $user->hasAnyRole($item['roles'] ?? []);
											});
										?>

										
										<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($visibleItems->isNotEmpty()): ?>
											<div data-kt-menu-trigger="click"
												class="menu-item menu-accordion <?php echo e(request()->is(...($menu['active_patterns'] ?? [])) ? 'show' : ''); ?>">

												<span class="menu-link">
													<span class="menu-icon">
														<i class="ki-duotone <?php echo e($menu['icon']); ?> fs-2">
															<span class="path1"></span>
															<span class="path2"></span>
														</i>
													</span>

													<span class="menu-title"><?php echo e(__($menu['title'])); ?></span>
													<span class="menu-arrow"></span>
												</span>

												<div class="menu-sub menu-sub-accordion">
													<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $visibleItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
														<div class="menu-item">
															<a class="menu-link <?php echo e(request()->is($item['pattern']) ? 'active' : ''); ?>"
																href="<?php echo e(url($item['url'])); ?>">
																<span class="menu-bullet">
																	<span class="bullet bullet-dot"></span>
																</span>
																<span class="menu-title"><?php echo e(__($item['title'])); ?></span>
															</a>
														</div>
													<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
												</div>
											</div>
										<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

									<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
								<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
							</div>
						</div>
					</div>
				</div>
				<!--end::sidebar menu-->

			</div>
			<!--end::Sidebar-->
			<!--begin::Main-->
			<div class="app-main flex-column flex-row-fluid" id="kt_app_main">
				<!--begin::Content wrapper-->
				<div class="d-flex flex-column flex-column-fluid">
					<!--begin::Toolbar-->
					<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
						<!--begin::Toolbar container-->
						<div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
							<!--begin::Page title-->
							<div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
								<!--begin::Title-->
								<h1
									class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
									<?php echo $__env->yieldContent('title'); ?></h1>
								<!--end::Title-->
								<!--begin::Breadcrumb-->
								<ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
									<!--begin::Item-->
									<li class="breadcrumb-item text-muted">
										<a href="<?php echo e(url('')); ?>"
											class="text-muted text-hover-primary"><?php echo e(__('ui.nav.home')); ?></a>
									</li>
									<!--end::Item-->
									<!--begin::Item-->
									<li class="breadcrumb-item">
										<span class="bullet bg-gray-400 w-5px h-2px"></span>
									</li>
									<!--end::Item-->
									<!--begin::Item-->
									<li class="breadcrumb-item text-muted"><?php echo $__env->yieldContent('pageName'); ?></li>
									<!--end::Item-->
								</ul>
								<!--end::Breadcrumb-->
							</div>
							<!--end::Page title-->
							<!--begin::Actions-->



							<!--end::Actions-->
						</div>
						<!--end::Toolbar container-->
					</div>
					<!--end::Toolbar-->
					<!--begin::Content-->
					<div id="kt_app_content" class="app-content flex-column-fluid">
						<!--begin::Content container-->
						<div id="kt_app_content_container" class="app-container container-fluid">

							<?php echo $__env->yieldContent('content'); ?>

						</div>
						<!--end::Content container-->
					</div>
					<!--end::Content-->
				</div>
				<!--end::Content wrapper-->
				<!--begin::Footer-->
				<div id="kt_app_footer" class="app-footer">
					<!--begin::Footer container-->
					<div
						class="app-container container-fluid d-flex flex-column flex-md-row flex-center flex-md-stack py-3">
						<!--begin::Copyright-->
						<div class="text-dark order-2 order-md-1">
							<span class="text-muted fw-semibold me-1">2023&copy;</span>
							<a href="https://keenthemes.com" target="_blank"
								class="text-gray-800 text-hover-primary">PHC</a>
						</div>
						<!--end::Copyright-->
						<!--begin::Menu-->

					</div>
					<!--end::Footer container-->
				</div>
				<!--end::Footer-->
			</div>
			<!--end:::Main-->
		</div>
		<!--end::Wrapper-->
	</div>
	<!--end::Page-->
	</div>
	<!--end::App-->
	<!--begin::Drawers-->
	<!--begin::Activities drawer-->


	<!--begin::Scrolltop-->
	<div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
		<i class="ki-duotone ki-arrow-up">
			<span class="path1"></span>
			<span class="path2"></span>
		</i>
	</div>
	<!--end::Scrolltop-->


	<!--end::Modal - Invite Friend-->
	<!--end::Modals-->
	<!--begin::Javascript-->
	<script>var hostUrl = "assets/";</script>
	<!--begin::Global Javascript Bundle(mandatory for all pages)-->
	<script src="<?php echo e(url('')); ?>/assets/plugins/global/plugins.bundle.js"></script>
	<script src="<?php echo e(url('')); ?>/assets/js/scripts.bundle.js"></script>
	<!--end::Global Javascript Bundle-->
	<!--begin::Vendors Javascript(used for this page only)-->
	<script src="<?php echo e(url('')); ?>/assets/plugins/custom/fullcalendar/fullcalendar.bundle.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/radar.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/map.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/geodata/worldLow.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/geodata/continentsLow.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/geodata/usaLow.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/geodata/worldTimeZonesLow.js"></script>
	<script src="https://cdn.amcharts.com/lib/5/geodata/worldTimeZoneAreasLow.js"></script>
	<script src="<?php echo e(url('')); ?>/assets/plugins/custom/datatables/datatables.bundle.js"></script>
	<!--end::Vendors Javascript-->
	<!--begin::Custom Javascript(used for this page only)-->
	<script src="<?php echo e(url('')); ?>/assets/js/widgets.bundle.js"></script>
	<script src="<?php echo e(url('')); ?>/assets/js/custom/widgets.js"></script>
	<script src="<?php echo e(url('')); ?>/assets/js/custom/apps/chat/chat.js"></script>
	<script src="<?php echo e(url('')); ?>/assets/js/custom/utilities/modals/upgrade-plan.js"></script>
	<script src="<?php echo e(url('')); ?>/assets/js/custom/utilities/modals/create-app.js"></script>
	<script src="<?php echo e(url('')); ?>/assets/js/custom/utilities/modals/new-target.js"></script>
	<script src="<?php echo e(url('')); ?>/assets/js/custom/utilities/modals/users-search.js"></script>

	<?php echo $__env->yieldContent('script'); ?>
	<!--end::Custom Javascript-->
	<!--end::Javascript-->


	<script>

		$(document).ready(function () {
			const persistedLocale = document.body.dataset.locale;

			if (persistedLocale) {
				localStorage.setItem('preferred_locale', persistedLocale);
			}

			document.querySelectorAll('.locale-switcher-form').forEach((form) => {
				form.addEventListener('submit', function () {
					const locale = this.dataset.locale;

					if (locale) {
						localStorage.setItem('preferred_locale', locale);
					}
				});
			});

			const searchRoot = $('#kt_header_search');
			const searchInput = $('#global-search-input');
			const searchSpinner = searchRoot.find('.search-spinner');
			const searchReset = searchRoot.find('.search-reset');
			const searchResults = searchRoot.find('[data-kt-search-element="results"]');
			const searchMain = searchRoot.find('[data-kt-search-element="main"]');
			const searchEmpty = searchRoot.find('[data-kt-search-element="empty"]');
			const searchResultsList = searchResults.find('.scroll-y').first();
			const searchEmptyTitle = searchEmpty.find('h3').first();
			const quickSearchLinks = <?php echo json_encode($quickSearchLinks, 15, 512) ?>;
			const noResultsTemplate = <?php echo json_encode(__('ui.search.no_results', ['query' => '__QUERY__']), 512) ?>;
			let debounceTimer = null;
			let activeRequest = null;

			const iconPaths = {
				'ki-element-11': '<span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>',
				'ki-home': '<span class="path1"></span><span class="path2"></span>',
				'ki-home-2': '<span class="path1"></span><span class="path2"></span>',
				'ki-office-bag': '<span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>',
				'ki-map': '<span class="path1"></span><span class="path2"></span><span class="path3"></span>',
				'ki-shield-tick': '<span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>',
				'ki-file-down': '<span class="path1"></span><span class="path2"></span>',
			};

			const escapeHtml = (value) => $('<div>').text(value ?? '').html();
			const iconMarkup = (icon) => iconPaths[icon] ?? '<span class="path1"></span><span class="path2"></span>';

			const renderQuickLinks = () => {
				let html = `
					<div class="d-flex flex-stack fw-semibold mb-4">
						<span class="text-muted fs-6 me-2">${escapeHtml(<?php echo json_encode(__('ui.search.quick_access'), 15, 512) ?>)}</span>
					</div>
					<div class="text-muted fs-7 mb-5">${escapeHtml(<?php echo json_encode(__('ui.search.start_typing'), 15, 512) ?>)}</div>
					<div class="scroll-y mh-200px mh-lg-325px">
				`;

				quickSearchLinks.forEach((link) => {
					html += `
						<a href="${escapeHtml(link.url)}" class="d-flex text-dark text-hover-primary align-items-center mb-5">
							<div class="symbol symbol-40px me-4">
								<span class="symbol-label bg-light-primary">
									<i class="ki-duotone ${escapeHtml(link.icon)} fs-2 text-primary">
										${iconMarkup(link.icon)}
									</i>
								</span>
							</div>
							<div class="d-flex flex-column">
								<span class="fs-6 fw-semibold">${escapeHtml(link.title)}</span>
								<span class="fs-7 text-muted fw-semibold">${escapeHtml(link.subtitle)}</span>
							</div>
						</a>
					`;
				});

				html += '</div>';

				searchMain.html(html);
			};

			const showMainState = () => {
				searchMain.removeClass('d-none');
				searchResults.addClass('d-none');
				searchEmpty.addClass('d-none');
				searchSpinner.addClass('d-none');
			};

			const renderResults = (results) => {
				let currentGroup = null;
				let html = '';

				results.forEach((result) => {
					if (currentGroup !== result.group) {
						currentGroup = result.group;
						html += `<h3 class="fs-5 text-muted m-0 ${html === '' ? 'pb-5' : 'pt-5 pb-5'}">${escapeHtml(result.group)}</h3>`;
					}

					html += `
						<a href="${escapeHtml(result.url)}" class="d-flex text-dark text-hover-primary align-items-center mb-5">
							<div class="symbol symbol-40px me-4">
								<span class="symbol-label bg-light-primary">
									<i class="ki-duotone ${escapeHtml(result.icon)} fs-2 text-primary">
										${iconMarkup(result.icon)}
									</i>
								</span>
							</div>
							<div class="d-flex flex-column">
								<span class="fs-6 fw-semibold">${escapeHtml(result.title)}</span>
								<span class="fs-7 text-muted fw-semibold">${escapeHtml(result.subtitle)}</span>
							</div>
						</a>
					`;
				});

				searchResultsList.html(html);
				searchMain.addClass('d-none');
				searchEmpty.addClass('d-none');
				searchResults.removeClass('d-none');
			};

			const showEmptyState = (query) => {
				searchMain.addClass('d-none');
				searchResults.addClass('d-none');
				searchEmptyTitle.text(noResultsTemplate.replace('__QUERY__', query));
				searchEmpty.removeClass('d-none');
			};

			renderQuickLinks();
			showMainState();

			searchInput.on('input', function () {
				const query = $(this).val().trim();

				searchReset.toggleClass('d-none', query.length === 0);

				if (activeRequest) {
					activeRequest.abort();
					activeRequest = null;
				}

				clearTimeout(debounceTimer);

				if (query.length < 2) {
					showMainState();
					return;
				}

				debounceTimer = setTimeout(function () {
					searchSpinner.removeClass('d-none');

					activeRequest = $.ajax({
						url: <?php echo json_encode(route('global-search'), 15, 512) ?>,
						method: 'GET',
						data: { search: query },
					}).done(function (response) {
						const results = response.results ?? [];

						if (results.length === 0) {
							showEmptyState(query);
							return;
						}

						renderResults(results);
					}).always(function () {
						searchSpinner.addClass('d-none');
						activeRequest = null;
					});
				}, 300);
			});

			searchReset.on('click', function () {
				searchInput.val('');
				searchResultsList.empty();
				showMainState();
			});
		});

		function showModal(formName, id) {

			$('#kt_modal_' + formName + '_form').find('select').val(null).trigger('change');

			if (id == null) {


				$('#kt_modal_' + formName).modal('show', { backdrop: 'static' });
				//  $('#kt_modal_' + formName + '_form').attr('action', "<?php echo e(url('')); ?>/" + formName);
				$('#kt_modal_' + formName + '_form').find('[name="id"]').val(null);
				//   $('#kt_modal_' + formName + '_form').bootstrapValidator('resetForm', true);
				// $('#kt_modal_' + formName + '_form').bootstrapValidator('enableFieldValidators', 'res_link', true);

			} else {
				//    $('#kt_modal_' + formName + '_form').bootstrapValidator('resetForm', true);

				$.get('<?php echo e(url('')); ?>' + "/" + formName + '/' + id + "/edit", function (data) {
					if (data) {

						try {
							var data = JSON.parse(data)

						} catch (err) {
							data

						}


						var selects = $('#kt_modal_' + formName + '_form').find('select').serializeArray();
						var inputs = $('#kt_modal_' + formName + '_form').find('input,textarea').serializeArray();
						var inputs = $('#kt_modal_' + formName + '_form').find('input,textarea').serializeArray();


						$.each(selects, function (i, field) {
							var fieldName = field.name

							$('#kt_modal_' + formName + '_form').find('[name="' + fieldName + '"]').val(data[formName][fieldName]).trigger('change');

						});
						$.each(inputs, function (i, field) {

							var fieldName = field.name

							$('#kt_modal_' + formName + '_form').find('[name="' + fieldName + '"]').val(data[formName][fieldName]);

						});
						if (formName == "user") {
							// $('#kt_modal_' + formName + '_form').bootstrapValidator('enableFieldValidators', 'res_link', false);
							var roles_ids = [];

							$.each(data[formName]['roles'], function (i, field) {
								roles_ids.push(field.id);

							});
							console.log(roles_ids)
							$('#kt_modal_' + formName + '_form').find('[name="roles[]"]').val(roles_ids).trigger('change');

						}
						$('#kt_modal_' + formName).modal('show', { backdrop: 'static' });
					}
				}).fail(function () {
				})
			}
		}
	</script>
</body>
<!--end::Body-->

</html>
<?php /**PATH D:\myProjects\phc\resources\views/layouts/app.blade.php ENDPATH**/ ?>