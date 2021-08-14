@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{!! ctrans('texts.stripe_connect_migration_title') !!}</h1>
        
        <p>{!! ctrans('texts.stripe_connect_migration_desc') !!}</p>
    </div>
@endcomponent
