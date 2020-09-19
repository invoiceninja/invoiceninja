@component('email.template.master', ['design' => 'light', 'settings' =>$settings])

@slot('header')
    @component('email.components.header', ['p' => 'Your quote was approved!', 'logo' => 'https://www.invoiceninja.com/wp-content/uploads/2019/01/InvoiceNinja-Logo-Round-300x300.png'])
        Quote approved
    @endcomponent

@endslot

@slot('greeting')
    Hello,
@endslot

We want to inform you that quote was approved. Put nicer text here.

@component('email.components.button', ['url' => 'https://invoiceninja.com', 'show_link' => true])
    Visit InvoiceNinja
@endcomponent

@slot('below_card')
    @component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
        For any info, please visit InvoiceNinja.
    @endcomponent
@endslot    

@endcomponent