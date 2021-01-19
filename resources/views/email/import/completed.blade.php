@component('email.template.master', ['design' => 'light', 'settings' => $settings])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <h1>Import completed</h1>
    <p>Hello, here is the output of your recent import job.</p>

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

    <a href="{{ url('/') }}" target="_blank" class="button">Visit portal</a>

    <p>Thank you, <br/> Invoice Ninja.</p>
@endcomponent
