@component('email.template.master', ['design' => 'light'])

@slot('header')
    @component('email.components.header', ['p' => '', 'logo' => 'https://www.invoiceninja.com/wp-content/uploads/2019/01/InvoiceNinja-Logo-Round-300x300.png'])
        
        @if(isset($title))
        {{$title}}
        @endif

    @endcomponent

{!! $body !!}

@slot('footer')

	@if(isset($footer))
		{!! $footer !!}
	@endif

    @component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
        For any info, please visit InvoiceNinja.
    @endcomponent
@endslot


@endcomponent
