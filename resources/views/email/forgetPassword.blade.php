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

<h2>نسيت كلمة المرور البريد الإلكتروني</h2>

<p>يمكنك إعادة تعيين كلمة المرور من الرابط أدناه:</p>
<a href="{{ route('reset.password.get', $token) }}">إعادة تعيين كلمة المرور</a>
