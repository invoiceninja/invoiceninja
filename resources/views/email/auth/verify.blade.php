@component('email.template.admin', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png', 'settings' => $settings])
    <div class="center">
        <p>{{ ctrans('texts.confirmation_message') }}</p>

        <a href="{{ url("/user/confirm/{$user->confirmation_code}") }}" target="_blank" class="button">
            {{ ctrans('texts.confirm') }}
        </a>
    </div>
@endcomponent
