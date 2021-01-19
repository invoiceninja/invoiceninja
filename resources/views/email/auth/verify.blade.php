@component('email.template.master', ['design' => 'light', 'whitelabel' => false])

    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <p>{{ ctrans('texts.confirmation_message') }}</p>

    <a href="{{ url("/user/confirm/{$user->confirmation_code}") }}" target="_blank" class="button">
        {{ ctrans('texts.confirm') }}
    </a>
@endcomponent
