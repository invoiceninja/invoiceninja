@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.download_files') }}</h1>
        <p>{{ ctrans('texts.download_report_description') }}</p>

    </div>
@endcomponent
