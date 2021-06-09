@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.login_link_requested_label') }}</h1>
        <p>{{ ctrans('texts.login_link_requested') }}</p>

        <a href="{{ $url }}" target="_blank" class="button">Sign in to Invoice Ninja</a>
    </div>
@endcomponent
