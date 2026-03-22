@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h2>{!! $title !!}</h2>
        <p>{!! $body !!}</p>
    </div>
@endcomponent
