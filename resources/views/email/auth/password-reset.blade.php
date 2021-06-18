@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <p>{{ ctrans('texts.reset_password') }}</p>

        <a href="{{ $link }}" target="_blank" class="button">
            {{ ctrans('texts.reset') }}
        </a>
    </div>
@endcomponent
