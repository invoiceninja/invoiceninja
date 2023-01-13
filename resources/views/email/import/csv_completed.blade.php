@component('email.template.admin', ['logo' => $logo, 'settings' => $settings, 'company' => $company ?? ''])
    <div class="center">
        <h1>{{ ctrans('texts.import_complete') }}</h1>

        <p><img src="{{ $logo }}"></p>

        @if($client_count)
            <p><b>{{ ctrans('texts.clients') }}:</b> {{ $client_count }} </p>
        @endif

        @if($product_count)
            <p><b>{{ ctrans('texts.products') }}:</b> {{ $product_count }} </p>
        @endif

        @if($invoice_count)
            <p><b>{{ ctrans('texts.invoices') }}:</b> {{ $invoice_count }} </p>
        @endif

        @if($payment_count)
            <p><b>{{ ctrans('texts.payments') }}:</b> {{ $payment_count }} </p>
        @endif

        @if($recurring_invoice_count)
            <p><b>{{ ctrans('texts.recurring_invoices') }}:</b> {{ $recurring_invoice_count }} </p>
        @endif

        @if($quote_count)
            <p><b>{{ ctrans('texts.quotes') }}:</b> {{ $quote_count }} </p>
        @endif

        @if($credit_count)
            <p><b>{{ ctrans('texts.credits') }}:</b> {{ $credit_count }} </p>
        @endif

        @if($project_count)
            <p><b>{{ ctrans('texts.projects') }}:</b> {{ $project_count }} </p>
        @endif

        @if($task_count)
            <p><b>{{ ctrans('texts.tasks') }}:</b> {{ $task_count }} </p>
        @endif

        @if($vendor_count)
            <p><b>{{ ctrans('texts.vendors') }}:</b> {{ $vendor_count }} </p>
        @endif

        @if($expense_count)
            <p><b>{{ ctrans('texts.expenses') }}:</b> {{ $expense_count }} </p>
        @endif

        @if($company_gateway_count)
            <p><b>{{ ctrans('texts.gateways') }}:</b> {{ $company_gateway_count }} </p>
        @endif

        @if($client_gateway_token_count)
            <p><b>{{ ctrans('texts.tokens') }}:</b> {{ $client_gateway_token_count }} </p>
        @endif

        @if($tax_rate_count)
            <p><b>{{ ctrans('texts.tax_rates') }}:</b> {{ $tax_rate_count }} </p>
        @endif

        @if($document_count)
            <p><b>{{ ctrans('texts.documents') }}:</b> {{ $document_count }} </p>
        @endif

        @if($transaction_count)
            <p><b>{{ ctrans('texts.documents') }}:</b> {{ $transaction_count }} </p>
        @endif

        @if(!empty($errors) )
            <p>{{ ctrans('texts.failed_to_import') }}</p>
            <p>{{ ctrans('texts.error') }}:</p>
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

        <p>{{ ctrans('texts.email_signature')}}</p>
        <p>{{ ctrans('texts.email_from') }}</p>
    </div>
@endcomponent

