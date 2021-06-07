@component('email.template.admin-light', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h2>{!! $title !!}</h2>
        <p>{!! $body !!}</p>
    </div>
@endcomponent
