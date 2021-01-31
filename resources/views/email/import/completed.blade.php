@component('email.template.master', ['design' => 'light', 'settings' => $settings])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <h1>Import completed</h1>
    <p>Hello, here is the output of your recent import job.</p>

    <p><b>If your logo imported correctly it will display below. If it didn't import, you'll need to reupload your logo</b></p>

    <p><img src="{{ $company->present()->logo() }}"></p>

    @if(isset($company) && count($company->clients) >=1)
        <p><b>Clients Imported:</b> {{ count($company->clients) }} </p>
    @endif

    @if(isset($company) && count($company->products) >=1)
        <p><b>Products Imported:</b> {{ count($company->products) }} </p>
    @endif

    @if(isset($company) && count($company->invoices) >=1)
        <p><b>Invoices Imported:</b> {{ count($company->invoices) }} </p>

        <p>To test your PDF generation is working correctly, click <a href="{{$company->invoices->first()->invitations->first()->getLink() }}">here</a>.  We've also attempted to attach the PDF to this email.

    @endif

    @if(isset($company) && count($company->payments) >=1)
        <p><b>Payments Imported:</b> {{ count($company->payments) }} </p>
    @endif

    @if(isset($company) && count($company->recurring_invoices) >=1)
        <p><b>Recurring Invoices Imported:</b> {{ count($company->recurring_invoices) }} </p>
    @endif

    @if(isset($company) && count($company->quotes) >=1)
        <p><b>Quotes Imported:</b> {{ count($company->quotes) }} </p>
    @endif

    @if(isset($company) && count($company->credits) >=1)
        <p><b>Credits Imported:</b> {{ count($company->credits) }} </p>
    @endif

    @if(isset($company) && count($company->projects) >=1)
        <p><b>Projects Imported:</b> {{ count($company->projects) }} </p>
    @endif

    @if(isset($company) && count($company->tasks) >=1)
        <p><b>Tasks Imported:</b> {{ count($company->tasks) }} </p>
    @endif

    @if(isset($company) && count($company->vendors) >=1)
        <p><b>Vendors Imported:</b> {{ count($company->vendors) }} </p>
    @endif

    @if(isset($company) && count($company->expenses) >=1)
        <p><b>Expenses Imported:</b> {{ count($company->expenses) }} </p>
    @endif

    @if(isset($company) && count($company->company_gateways) >=1)
        <p><b>Gateways Imported:</b> {{ count($company->company_gateways) }} </p>
    @endif

    @if(isset($company) && count($company->client_gateway_tokens) >=1)
        <p><b>Client Gateway Tokens Imported:</b> {{ count($company->client_gateway_tokens) }} </p>
    @endif

    @if(isset($company) && count($company->tax_rates) >=1)
        <p><b>Tax Rates Imported:</b> {{ count($company->tax_rates) }} </p>
    @endif

    @if(isset($company) && count($company->documents) >=1)
        <p><b>Documents Imported:</b> {{ count($company->documents) }} </p>
    @endif

    <a href="{{ url('/') }}" target="_blank" class="button">{{ ctrans('texts.account_login')}}</a>

    <p>{{ ctrans('texts.email_signature')}}<br/> {{ ctrans('texts.email_from') }}</p>
@endcomponent
