@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.max_companies') }}</h1>
        
        <p>{{ ctrans('texts.max_companies_desc') }}</p>
    </div>
@endcomponent
