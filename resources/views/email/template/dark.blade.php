@component('email.template.master', ['design' => 'dark', 'settings' => $settings, 'whitelabel' => $whitelabel])

    @slot('header')
        @include('email.components.header', ['logo' => (strlen($settings->company_logo) > 1) ? url('') . $settings->company_logo : 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    {!! $body !!}

    @slot('below_card')
        @if($signature)
            {{ $signature }}
        @endif
    @endslot

@endcomponent
