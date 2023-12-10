@component('email.template.admin', ['logo' => $logo, 'settings' => $settings, 'company' => $company ?? ''])
    <div class="center">
        <h1>{{ ctrans('texts.import_complete') }}</h1>
        <p>Hello, here is the output of your recent import job.</p>

        <p><b>If your logo imported correctly it will display below. If it didn't import, you'll need to reupload your logo</b></p>

        <p><img src="{{ $logo }}"></p>

        @if(isset($company))
            <p><b>{{ ctrans('texts.clients') }}:</b> {{ $client_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.products') }}:</b> {{ $product_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.invoices') }}:</b> {{ $invoice_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.payments') }}:</b> {{ $payment_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.recurring_invoices') }}:</b> {{ $recurring_invoice_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.quotes') }}:</b> {{ $quote_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.credits') }}:</b> {{ $credit_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.projects') }}:</b> {{ $project_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.tasks') }}:</b> {{ $task_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.vendors') }}:</b> {{ $vendor_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.expenses') }}:</b> {{ $expense_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.gateways') }}:</b> {{ $company_gateway_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.tokens') }}:</b> {{ $client_gateway_token_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.tax_rates') }}:</b> {{ $tax_rate_count }} </p>
        @endif

        @if(isset($company))
            <p><b>{{ ctrans('texts.documents') }}:</b> {{ $document_count }} </p>
        @endif

        @if(isset($check_data))
            <p><b>Data Quality:</b></p>
            <p> {!! $check_data !!} </p>
        @endif

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

        <div>
            <!--[if (gte mso 9)|(IE)]>
                <table align="center" cellspacing="0" cellpadding="0" style="width: 600px;">
                    <tr>
                        <td align="center" valign="top">
                            <![endif]-->        
                            <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" >
                                <tbody>
                                    <tr>
                                    <td align="center" class="new_button" style="border-radius: 2px; background-color: '.$this->settings->primary_color.'">
                                        <a href="{{ $url }}" target="_blank" class="new_button" style="text-decoration: none; border: 1px solid '.$this->settings->primary_color.'; display: inline-block; border-radius: 2px; padding-top: 15px; padding-bottom: 15px; padding-left: 25px; padding-right: 25px; font-size: 20px; color: #fff">
                                        <singleline label="cta button">{{ ctrans('texts.account_login') }}</singleline>
                                        </a>
                                    </td>
                                    </tr>
                                </tbody>
                            </table>
                            <!--[if (gte mso 9)|(IE)]>
                        </td>
                    </tr>
                </table>
            <![endif]-->
        </div>

        <p>{{ ctrans('texts.email_signature')}}</p>
        <p>{{ ctrans('texts.email_from') }}</p>
    </div>
@endcomponent

