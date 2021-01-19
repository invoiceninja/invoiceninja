@component('email.template.master', ['design' => 'light', 'whitelabel' => false])

    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <p>You are receiving this email because we received a password reset request for your account.</p>

    <a href="{{ $link }}" target="_blank" class="button">
        Reset Password
    </a>

    <p>
        If you’re having trouble clicking the "Reset Password" button, copy and paste the URL below into your web
        browser:
    </p>

    <a href="{{ $link }}">{{ $link }}</a>
@endcomponent
