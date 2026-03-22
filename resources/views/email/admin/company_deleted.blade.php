@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{!! $title !!}</h1>
        <p>{!! $body !!}</p>
    </div>
@endcomponent
