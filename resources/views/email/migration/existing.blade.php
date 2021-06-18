@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.migration_already_completed') }}</h1>
        <p>{!! ctrans('texts.migration_already_completed_desc', ['company_name' => $company_name]) !!}</p>
    </div>
@endcomponent
