<style>
    @font-face {
        font-family: 'Droid Arabic Kufi';
        src: url('DroidArabicKufi.eot');
        src: url('DroidArabicKufi.eot?#iefix') format('embedded-opentype'),
        url({{url('DroidArabicKufi.woff')}}) format('woff'),
        url({{url('DroidArabicKufi.ttf')}}) format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    h2, a , p {
        font-family: 'Droid Arabic Kufi' !important;

    }
</style>

<h2>{{ __('ui.mail.forgot_password_title') }}</h2>

<p>{{ __('ui.mail.forgot_password_text') }}</p>
<a href="{{ route('reset.password.get', $token) }}">{{ __('ui.mail.reset_password') }}</a>
