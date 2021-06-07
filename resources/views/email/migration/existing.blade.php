@component('email.template.admin-light', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h2>{{ ctrans('texts.migration_already_completed') }}</h2>
        <p>{{ ctrans('texts.migration_already_completed_desc', ['company_name' => $company_name]) }}</p>
    </div>
@endcomponent
