@php
    $isRtl = app()->getLocale() === 'ar';
    $direction = $isRtl ? 'rtl' : 'ltr';
    $suffix = $isRtl ? '.rtl' : '';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction }}" style="direction: {{ $direction }}">
<head>
    <base href="../../../" />
    <title>{{ __('ui.auth.reset_password') }} - {{ __('ui.app.name') }}</title>
    <meta charset="utf-8" />
    <meta name="description" content="{{ __('ui.auth.reset_password') }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    @include('pwa.head')
    <link rel="shortcut icon" href="{{ asset('assets/media/logos/favicon.ico') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/fontface.css') }}">
    <link href="{{ asset('assets/plugins/global/plugins.bundle' . $suffix . '.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.bundle' . $suffix . '.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/font-unified.css') }}" rel="stylesheet" type="text/css" />
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
                    <div class="mb-7">
                        <a href="{{ route('login', [], false) }}">
                            <img style="max-width: 400px; height: auto;" class="h-100px" alt="Logo" src="{{ asset('assets/media/logos/LogoGaza2.jpeg') }}" />
                        </a>
                    </div>

                    <h1 class="text-white fw-normal m-0">{{ __('ui.app.name') }}</h1>
                </div>
            </div>

            <div class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12 p-lg-20">
                <div class="bg-body d-flex flex-column align-items-stretch flex-center rounded-4 w-md-600px p-20">
                    <div class="d-flex flex-center flex-column flex-column-fluid px-lg-10 pb-15 pb-lg-20">
                        <form class="form w-100" method="POST" action="{{ route('password.store') }}">
                            @csrf
                            <input type="hidden" name="token" value="{{ $request->route('token') }}">

                            <div class="text-center mb-11">
                                <h1 class="text-dark fw-bolder mb-3">{{ __('ui.auth.reset_password') }}</h1>
                            </div>

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
                                <input type="email" placeholder="{{ __('ui.auth.email') }}" name="email" value="{{ old('email', $request->email) }}"
                                    autocomplete="username" class="form-control bg-transparent @error('email') is-invalid @enderror" required autofocus />
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="fv-row mb-8">
                                <input type="password" placeholder="{{ __('ui.auth.password') }}" name="password"
                                    autocomplete="new-password" class="form-control bg-transparent @error('password') is-invalid @enderror" required />
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="fv-row mb-8">
                                <input type="password" placeholder="{{ __('ui.auth.password_confirmation') }}" name="password_confirmation"
                                    autocomplete="new-password" class="form-control bg-transparent @error('password_confirmation') is-invalid @enderror" required />
                                @error('password_confirmation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-grid mb-10">
                                <button type="submit" class="btn btn-primary">{{ __('ui.auth.reset_password') }}</button>
                            </div>

                            <div class="text-center">
                                <a href="{{ route('login', [], false) }}" class="link-primary">{{ __('ui.auth.back_to_login') }}</a>
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
    @include('pwa.scripts')
</body>
</html>
