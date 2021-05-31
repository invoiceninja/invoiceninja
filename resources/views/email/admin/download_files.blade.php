@component('email.template.master', ['design' => 'light', 'settings' =>$settings])

@slot('header')
    @component('email.components.header', ['p' => '', 'logo' => $logo])
    	@lang('texts.download')
    @endcomponent

@endslot


@if(isset($greeting))
<p style="padding-top:20px">{{ $greeting }}</p>
@endif

<p style="padding-top:20px">
@lang('texts.download_timeframe')
</p>

<p style="padding-top:20px">
    @component('email.components.button', ['url' => $url])
        @lang('texts.download')
    @endcomponent
</p>

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