@component('email.template.master', ['design' => 'light'])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <h2>Login link requested</h2>
    <p>Hey, there was a request to log in using link.</p>

    <a href="{{ $url }}" target="_blank" class="button">Sign in to Invoice Ninja</a>

    <span style="margin-top: 35px; display: block;">Link above is only for you. Don't share it anyone.</span>
    <span>If you didn't request this, just ignore it.</span>

    <span style="margin-top: 25px; display: block;">If you can't click on the button, copy following link:</span>
    <a href="{{ $url }}">{{ $url }}</a>
@endcomponent
