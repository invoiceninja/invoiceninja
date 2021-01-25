@component('email.template.master', ['design' => 'light', 'settings' => $settings])


    @slot('header')
        @include('email.components.header', ['logo' => $logo])
    @endslot

    <p>{{ $title }}</p>

    @slot('greeting')
        @lang($message)
    @endslot

    @component('email.components.button', ['url' => $url])
        @lang($button)
    @endcomponent

    @slot('signature')
        {{ $signature }}
    @endslot

    @slot('footer')
        @component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
            For any info, please visit InvoiceNinja.
        @endcomponent
    @endslot
@endcomponent
