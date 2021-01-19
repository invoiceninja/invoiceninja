@component('email.template.master', ['design' => 'light', 'settings' => $settings])

@slot('header')
    @component('email.components.header')
        Migration already completed
    @endcomponent
@endslot

@slot('greeting')
	Hello,
@endslot

Looks like you already migrated your data to V2 version of the Invoice Ninja. In case you want to start over, you can 'force' migrate to wipe existing data.

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
