@component('email.template.admin', ['logo' => 'https://invoicing.co/images/invoiceninja-black-logo-2.png', 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.migration_completed')}}</h1>
        <p>{{ ctrans('texts.migration_completed_description')}}</p>

        <a href="{{ url('/') }}" target="_blank" class="button">
            {{ ctrans('texts.account_login')}}
        </a>

        <p>{{ ctrans('texts.email_signature')}}<br/> {{ ctrans('texts.email_from') }}</p>
    </div>
@endcomponent
