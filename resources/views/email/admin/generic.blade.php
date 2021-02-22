@component('email.template.master', ['design' => 'light', 'settings' => $settings])

    @slot('header')
        @include('email.components.header', ['logo' => $logo])
    @endslot

    @if(isset($greeting))
    <p>{{ $greeting }}</p>
    @endif

    <h2>{{ $title }}</h2>

    <p>{{ $message }}</p>

    @if(isset($additional_info))

        <p> {{ $additional_info }}</p>

    @endif

    @component('email.components.button', ['url' => $url])
        @lang($button)
    @endcomponent

    @slot('signature')
        {{ $signature }}
    @endslot

    @if(isset($whitelabel) && !$whitelabel)
        @slot('footer')
            @component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
                For any info, please visit InvoiceNinja.
            @endcomponent
        @endslot
    @endif
@endcomponent
