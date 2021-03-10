@component('email.template.master', ['design' => 'light', 'settings' => $settings, 'whitelabel' => $whitelabel])

@slot('header')
    @include('email.components.header', ['logo' => $company->present()->logo($settings)])
@endslot

{!! $body !!}

@slot('below_card')
    @if($signature)
        {{ $signature }}
    @endif
@endslot

@endcomponent
