{{ ctrans('texts.import_complete') }}

    @if($client_count)
        {{ ctrans('texts.clients') }}: {{ $client_count }}
    @endif

    @if($product_count)
        {{ ctrans('texts.products') }}: {{ $product_count }}
    @endif

    @if($invoice_count)
        {{ ctrans('texts.invoices') }}: {{ $invoice_count }}
    @endif

    @if($payment_count)
        {{ ctrans('texts.payments') }}: {{ $payment_count }}
    @endif

    @if($recurring_invoice_count)
        {{ ctrans('texts.recurring_invoices') }}: {{ $recurring_invoice_count }}
    @endif

    @if($quote_count)
        {{ ctrans('texts.quotes') }}: {{ $quote_count }}
    @endif

    @if($credit_count)
        {{ ctrans('texts.credits') }}: {{ $credit_count }}
    @endif

    @if($project_count)
        {{ ctrans('texts.projects') }}: {{ $project_count }}
    @endif

    @if($task_count)
        {{ ctrans('texts.tasks') }}: {{ $task_count }}
    @endif

    @if($vendor_count)
        {{ ctrans('texts.vendors') }}: {{ $vendor_count }}
    @endif

    @if($expense_count)
        {{ ctrans('texts.expenses') }}: {{ $expense_count }}
    @endif

    @if($company_gateway_count)
        {{ ctrans('texts.gateways') }}: {{ $company_gateway_count }}
    @endif

    @if($client_gateway_token_count)
        {{ ctrans('texts.tokens') }}: {{ $client_gateway_token_count }}
    @endif

    @if($tax_rate_count)
        {{ ctrans('texts.tax_rates') }}: {{ $tax_rate_count }}
    @endif

    @if($document_count)
        {{ ctrans('texts.documents') }}: {{ $document_count }}
    @endif

    @if($transaction_count)
        {{ ctrans('texts.documents') }}: {{ $transaction_count }}
    @endif

    @if(!empty($errors))
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

{!! url('/') !!}

{!! ctrans('texts.email_signature') !!}

{!! ctrans('texts.email_from') !!}


