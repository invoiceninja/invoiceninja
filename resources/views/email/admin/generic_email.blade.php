@component('email.template.master', ['design' => 'light', 'settings' =>$settings])

@slot('header')
    @component('email.components.header', ['p' => $title, 'logo' => $logo])
    @endcomponent
@endslot

@slot('greeting')
	@lang($body)
@endslot

    @if(isset($view_link))
    @component('email.components.button', ['url' => $view_link])
        {{ $view_text }}
    @endcomponent
    @endif

    @if(isset($signature))
    {{ $signature }}
    @endif

@slot('footer')
    @component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
        For any info, please visit InvoiceNinja.
    @endcomponent
@endslot

@endcomponent