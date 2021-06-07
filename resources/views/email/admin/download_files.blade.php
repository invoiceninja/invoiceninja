@component('email.template.admin-light', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.download') }}</h1>
        <p>{{ ctrans('texts.download_timeframe') }}</p>

        <a target="_blank" class="button" href="{{ $url }}">
            {{ ctrans('texts.download') }}
        </a>
    </div>
@endcomponent
