@component('email.template.master', ['design' => 'light', 'settings' =>$settings])

@slot('header')
    @component('email.components.header')
        Import completed
    @endcomponent
@endslot

@slot('greeting')
	Hello,
@endslot

Here is the output of your recent import job. <br><br>

@if(isset($clients) && count($clients) >=1)

<h3>Clients Imported: {{ count($clients) }} </h3>

@endif

@if(isset($errors['clients']) && count($errors['clients']) >=1)

    <h3>Client Errors</h3>

    <ul>
    @foreach($errors['clients'] as $error)
        <li>{{ $error['client'] }} - {{ $error['error'] }}</li>
    @endforeach
    </ul>
@endif

@if(isset($invoices) && count($invoices) >=1)

<h3>Invoices Imported: {{ count($invoices) }} </h3>

@endif

@if(isset($errors['invoices']) && count($errors['invoices']) >=1)

    <h3>Invoices Errors</h3>

    <ul>
    @foreach($errors['invoices'] as $error)
        <li>{{ $error['invoice'] }} - {{ $error['error'] }}</li>
    @endforeach
    </ul>
@endif

@if(isset($products) && count($products) >=1)

<h3>Products Imported: {{ count($products) }} </h3>

@endif

@if(isset($errors['products']) && count($errors['products']) >=1)

    <h3>Client Errors</h3>

    <ul>
    @foreach($errors['products'] as $error)
        <li>{{ $error['product'] }} - {{ $error['error'] }}</li>
    @endforeach
    </ul>
@endif

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