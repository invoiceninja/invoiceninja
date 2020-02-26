@component('email.template.master', ['design' => 'light'])

@slot('header')
    @component('email.components.header', ['p' => 'Your upgrade has completed!', 'logo' => 'https://www.invoiceninja.com/wp-content/uploads/2019/01/InvoiceNinja-Logo-Round-300x300.png'])
        Upgrade!
    @endcomponent

@endslot

@slot('greeting')
    Hello, David
@endslot

Hello, this is really tiny template. We just want to inform you that upgrade has been completed.

@component('email.components.button', ['url' => 'https://invoiceninja.com', 'show_link' => true])
    Visit InvoiceNinja
@endcomponent

@component('email.components.table')
| Laravel       | Table         | Example  |
| ------------- |:-------------:| --------:|
| Col 2 is      | Centered      | $10      |
| Col 3 is      | Right-Aligned | $20      |
@endcomponent

@slot('signature')
    Benjamin, InvoiceNinja (ben@invoiceninja.com)
@endslot

@slot('footer')
    @component('email.components.footer', ['url' => 'https://invoiceninja.com', 'url_text' => '&copy; InvoiceNinja'])
        For any info, please visit InvoiceNinja.
    @endcomponent
@endslot

@slot('below_card')
    Lorem ipsum dolor sit amet. I love InvoiceNinja.
@endslot    

@endcomponent