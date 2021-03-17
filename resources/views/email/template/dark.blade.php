@component('email.template.master', ['design' => 'dark', 'settings' => $settings, 'whitelabel' => $whitelabel])

    @slot('header')
        @include('email.components.header', ['logo' => $company->present()->logo($settings)])
    @endslot

    {!! $body !!}

@if($signature)
<br>
<br>
<p>
    {!! $signature !!}
</p>
@endif

@endcomponent
