@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.document_download_subject') }}</h1>
        <p>{{ ctrans('texts.download_timeframe') }}</p>

        <a target="_blank" class="button" href="{{ $url }}">
            {{ ctrans('texts.download') }}
        </a>
    </div>
@endcomponent
