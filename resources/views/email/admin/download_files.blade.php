@component('email.components.layout')

@slot('header')
    @component('email.components.header', ['p' => ''])
        <img src="{{ $logo }}" alt="Company Logo" style="display: block">
    @endcomponent
@endslot

@lang('texts.download_timeframe')

@component('email.components.button', ['url' => $url])
    @lang('texts.download')
@endcomponent

@slot('signature')
    InvoiceNinja
@endslot

@slot('footer')
    @component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
        For any info, please visit InvoiceNinja.
    @endcomponent
@endslot

@endcomponent