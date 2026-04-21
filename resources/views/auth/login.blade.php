@php
    $isRtl = app()->getLocale() === 'ar';
    $direction = $isRtl ? 'rtl' : 'ltr';
    $suffix = $isRtl ? '.rtl' : '';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction }}" style="direction: {{ $direction }}">
<head>
    <base href="../../../" />
    <title>{{ __('ui.app.damage_program') }} - {{ __('ui.app.name') }}</title>
    <meta charset="utf-8" />
    <meta name="description" content="{{ __('ui.app.damage_program') }} - {{ __('ui.app.name') }}" />
    <meta name="keywords" content="{{ __('ui.app.damage_program') }} - {{ __('ui.app.name') }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:locale" content="{{ $isRtl ? 'ar_PS' : 'en_US' }}" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="{{ __('ui.app.damage_program') }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:site_name" content="{{ __('ui.app.name') }}" />
    <link rel="shortcut icon" href="{{ asset('assets/media/logos/favicon.ico') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/fontface.css') }}">
    <link href="{{ asset('assets/plugins/global/plugins.bundle' . $suffix . '.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.bundle' . $suffix . '.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/font-unified.css') }}" rel="stylesheet" type="text/css" />
    <script>if (window.top != window.self) { window.top.location.replace(window.self.location.href); }</script>
</head>
<body id="kt_body" class="app-blank bgi-size-cover bgi-attachment-fixed bgi-position-center bgi-no-repeat">
    <script>var defaultThemeMode = "light"; var themeMode; if (document.documentElement) { if (document.documentElement.hasAttribute("data-bs-theme-mode")) { themeMode = document.documentElement.getAttribute("data-bs-theme-mode"); } else { if (localStorage.getItem("data-bs-theme") !== null) { themeMode = localStorage.getItem("data-bs-theme"); } else { themeMode = defaultThemeMode; } } if (themeMode === "system") { themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"; } document.documentElement.setAttribute("data-bs-theme", themeMode); }</script>

    <div class="d-flex flex-column flex-root" id="kt_app_root">
        <style>
            body {
                background-image: url('{{ asset('assets/media/auth/bg4.jpg') }}');
            }

            [data-bs-theme="dark"] body {
                background-image: url('{{ asset('assets/media/auth/bg4-dark.jpg') }}');
            }
        </style>

        <div class="d-flex flex-column flex-column-fluid flex-lg-row">
            <div class="d-flex flex-center w-lg-50 pt-15 pt-lg-0 px-10">
                <div class="d-flex flex-center flex-lg-start flex-column">
                    <div class="mb-7 d-flex align-items-center gap-3">
                        <a href="{{ route('login') }}">
                            <img style="max-width: 400px; height: auto;" class="h-100px" alt="Logo" src="{{ asset('assets/media/logos/LogoGaza2.jpeg') }}" />
                        </a>

                        <div class="btn-group" role="group" aria-label="{{ __('ui.locale.switcher') }}">
                            @foreach(config('app.supported_locales', ['en']) as $locale)
                                <form method="POST" action="{{ route('locale.update', $locale) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ app()->getLocale() === $locale ? 'btn-primary' : 'btn-light' }}">
                                        {{ __('ui.locale.' . ($locale === 'ar' ? 'arabic' : 'english')) }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>

                    <h1 class="text-white fw-normal m-0">{{ __('ui.app.name') }}</h1>
                </div>
            </div>

            <div class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12 p-lg-20">
                <div class="bg-body d-flex flex-column align-items-stretch flex-center rounded-4 w-md-600px p-20">
                    <div class="d-flex flex-center flex-column flex-column-fluid px-lg-10 pb-15 pb-lg-20">
                        <form class="form w-100" data-kt-redirect-url="{{ url('/') }}" novalidate="novalidate" id="kt_sign_in_form" method="POST" action="{{ route('login') }}">
                            @csrf
                            <input type="hidden" name="remember" value="true" />

                            <div class="text-center mb-11">
                                <h1 class="text-dark fw-bolder mb-3">{{ __('ui.auth.sign_in') }}</h1>
                            </div>

                            @if (session('error'))
                                <div class="alert alert-danger">{{ session('error') }}</div>
                            @endif

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="fv-row mb-8">
                                <input type="email" placeholder="{{ __('ui.auth.email') }}" name="email" value="{{ old('email') }}"
                                    autocomplete="off" class="form-control bg-transparent @error('email') is-invalid @enderror" required autofocus />
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="fv-row mb-3">
                                <input type="password" placeholder="{{ __('ui.auth.password') }}" name="password" autocomplete="off"
                                    class="form-control bg-transparent @error('password') is-invalid @enderror" required />
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
                                <div>
                                    <label class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="remember" />
                                        <span class="form-check-label text-gray-700 fs-base ms-1">{{ __('ui.auth.remember_me') }}</span>
                                    </label>
                                </div>
                                <a href="{{ route('password.request') }}" class="link-primary">{{ __('ui.auth.forgot_password') }}</a>
                            </div>

                            <div class="d-grid mb-10">
                                <button type="submit" id="kt_sign_in_submit" class="btn btn-primary">
                                    <span class="indicator-label">{{ __('ui.auth.sign_in') }}</span>
                                    <span class="indicator-progress">{{ __('ui.auth.please_wait') }}
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>var hostUrl = "assets/";</script>
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/custom/authentication/sign-in/general.js') }}"></script>
</body>
</html>
