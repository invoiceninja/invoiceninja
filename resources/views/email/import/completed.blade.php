@component('email.template.master', ['design' => 'light', 'settings' => $company->settings])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <h1>Import completed</h1>
    <p>Hello, here is the output of your recent import job.</p>

    <p><b>If your logo imported correctly it will display below. If it didn't import, you'll need to reupload your logo</b></p>

    <p><img src="{{ $company->present()->logo() }}"></p>

    @if(isset($company) && $company->clients->count() >=1)
        <p><b>{{ ctrans('texts.clients') }}:</b> {{ $company->clients->count() }} </p>
    @endif

    @if(isset($company) && count($company->products) >=1)
        <p><b>{{ ctrans('texts.products') }}:</b> {{ count($company->products) }} </p>
    @endif

    @if(isset($company) && count($company->invoices) >=1)
        <p><b>{{ ctrans('texts.invoices') }}:</b> {{ count($company->invoices) }} </p>
    @endif

    @if(isset($company) && count($company->payments) >=1)
        <p><b>{{ ctrans('texts.payments') }}:</b> {{ count($company->payments) }} </p>
    @endif

    @if(isset($company) && count($company->recurring_invoices) >=1)
        <p><b>{{ ctrans('texts.recurring_invoices') }}:</b> {{ count($company->recurring_invoices) }} </p>
    @endif

    @if(isset($company) && count($company->quotes) >=1)
        <p><b>{{ ctrans('texts.quotes') }}:</b> {{ count($company->quotes) }} </p>
    @endif

    @if(isset($company) && count($company->credits) >=1)
        <p><b>{{ ctrans('texts.credits') }}:</b> {{ count($company->credits) }} </p>
    @endif

    @if(isset($company) && count($company->projects) >=1)
        <p><b>{{ ctrans('texts.projects') }}:</b> {{ count($company->projects) }} </p>
    @endif

    @if(isset($company) && count($company->tasks) >=1)
        <p><b>{{ ctrans('texts.tasks') }}:</b> {{ count($company->tasks) }} </p>
    @endif

    @if(isset($company) && count($company->vendors) >=1)
        <p><b>{{ ctrans('texts.vendors') }}:</b> {{ count($company->vendors) }} </p>
    @endif

    @if(isset($company) && count($company->expenses) >=1)
        <p><b>{{ ctrans('texts.expenses') }}:</b> {{ count($company->expenses) }} </p>
    @endif

    @if(isset($company) && count($company->company_gateways) >=1)
        <p><b>{{ ctrans('texts.gateways') }}:</b> {{ count($company->company_gateways) }} </p>
    @endif

    @if(isset($company) && count($company->client_gateway_tokens) >=1)
        <p><b>{{ ctrans('texts.tokens') }}:</b> {{ count($company->client_gateway_tokens) }} </p>
    @endif

    @if(isset($company) && count($company->tax_rates) >=1)
        <p><b>{{ ctrans('texts.tax_rates') }}:</b> {{ count($company->tax_rates) }} </p>
    @endif

    @if(isset($company) && count($company->documents) >=1)
        <p><b>{{ ctrans('texts.documents') }}:</b> {{ count($company->documents) }} </p>
    @endif

    <p><b>Data Quality:</b></p>
    <p> {!! $check_data !!} </p>

    @if(!empty($errors) )
        <p>{{ ctrans('texts.errors') }}:</p>
        <table>
            <thead>
            <tr>
                <th>Type</th>
                <th>Data</th>
                <th>Error</th>
            </tr>
            </thead>
            <tbody>
            @foreach($errors as $entityType=>$entityErrors)
                @foreach($entityErrors as $error)
                    <tr>
                        <td>{{$entityType}}</td>
                        <td>{{json_encode($error[$entityType]??null)}}</td>
                        <td>{{json_encode($error['error'])}}</td>
                    </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
    @endif

    <a href="{{ url('/') }}" target="_blank" class="button">{{ ctrans('texts.account_login')}}</a>

    <p>{{ ctrans('texts.email_signature')}}<br/> {{ ctrans('texts.email_from') }}</p>

@endcomponent
