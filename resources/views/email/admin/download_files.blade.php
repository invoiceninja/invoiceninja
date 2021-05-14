@component('email.template.master', ['design' => 'light', 'settings' =>$settings])

@slot('header')
    @component('email.components.header', ['p' => '', 'logo' => $logo])
    	@lang('texts.download')
    @endcomponent

@endslot

@slot('greeting')
@endslot

@lang('texts.download_timeframe')


    @component('email.components.button', ['url' => $url])
        @lang('texts.download')
    @endcomponent

@slot('signature')
    InvoiceNinja (contact@invoiceninja.com)
@endslot

@if(!$whitelabel)
	@slot('footer')
	    @component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
	        For any info, please visit InvoiceNinja.
	    @endcomponent
	@endslot
@endif
@endcomponent