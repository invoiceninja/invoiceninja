@component('email.template.master', ['design' => 'light', 'settings' =>$settings])

@slot('header')
    @component('email.components.header')
        Migration completed
    @endcomponent
@endslot

@slot('greeting')
	Hello,
@endslot

We're happy to inform you that migration has been completed successfully. It is ready for you to review it.

@component('email.components.button', ['url' => url('/')])
    Visit portal
@endcomponent


@slot('signature')
Thank you, <br>
Invoice Ninja    
@endslot

@slot('footer')
    @component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
        For any info, please visit InvoiceNinja.
    @endcomponent
@endslot

@endcomponent