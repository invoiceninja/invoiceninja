@component('email.template.master', ['design' => 'light', 'settings' =>$settings])

@slot('header')
    @component('email.components.header', ['p' => $title, 'logo' => $logo])
    @endcomponent
@endslot

<p>
	{!! $message !!}
</p>

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