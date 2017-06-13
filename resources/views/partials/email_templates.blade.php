<script type="text/javascript">

    function renderEmailTemplate(str, invoice) {
        if (!str) {
            return '';
        }

        var passwordHtml = "{!! $account->isPro() && $account->enable_portal_password && $account->send_portal_password?'<br/>'.trans('texts.password').': XXXXXXXXX<br/>':'' !!}";

        @if ($account->isPro())
            var documentsHtml = "{!! trans('texts.email_documents_header').'<ul><li><a>'.trans('texts.email_documents_example_1').'</a></li><li><a>'.trans('texts.email_documents_example_2').'</a></li></ul>' !!}";
        @else
            var documentsHtml = "";
        @endif

        var keys = {
            'footer': {!! json_encode($account->getEmailFooter()) !!},
            'emailSignature': {!! json_encode($account->getEmailFooter()) !!},
            'account': "{{ $account->getDisplayName() }}",
            'dueDate': invoice ? invoice.due_date : "{{ $account->formatDate($account->getDateTime()) }}",
            'invoiceDate': invoice ? invoice.invoice_date : "{{ $account->formatDate($account->getDateTime()) }}",
            'client': invoice ? getClientDisplayName(invoice.client) : "{{ trans('texts.client_name') }}",
            'amount': invoice ? formatMoneyInvoice(parseFloat(invoice.partial) || parseFloat(invoice.balance_amount), invoice) : formatMoneyAccount(100, account),
            'balance': invoice ? formatMoneyInvoice(parseFloat(invoice.balance), invoice) : formatMoneyAccount(100, account),
            'total': invoice ? formatMoneyInvoice(parseFloat(invoice.amount), invoice) : formatMoneyAccount(100, account),
            'contact': invoice ? getContactDisplayName(invoice.client.contacts[0]) : 'Contact Name',
            'firstName': invoice ? invoice.client.contacts[0].first_name : 'First Name',
            'invoice': invoice ? invoice.invoice_number : '0001',
            'quote': invoice ? invoice.invoice_number : '0001',
            'password': passwordHtml,
            'documents': documentsHtml,
            'viewLink': '{{ link_to('#', url('/view/...')) }}$password',
            'viewButton': invoice && invoice.invoice_type_id == {{ INVOICE_TYPE_QUOTE }} ?
                '{!! Form::flatButton('view_quote', '#0b4d78') !!}$password' :
                '{!! Form::flatButton('view_invoice', '#0b4d78') !!}$password',
            'paymentLink': '{{ link_to('#', url('/payment/...')) }}$password',
            'paymentButton': '{!! Form::flatButton('pay_now', '#36c157') !!}$password',
            'autoBill': '{{ trans('texts.auto_bill_notification_placeholder') }}',
            'portalLink': "{{ URL::to('/client/portal/...') }}",
            'portalButton': '{!! Form::flatButton('view_portal', '#36c157') !!}',
            'customClient1': invoice ? invoice.client.custom_value1 : 'custom value',
            'customClient2': invoice ? invoice.client.custom_value2 : 'custom value',
            'customContact1': invoice ? invoice.client.contacts[0].custom_value1 : 'custom value',
            'customContact2': invoice ? invoice.client.contacts[0].custom_value2 : 'custom value',
            'customInvoice1': invoice ? invoice.custom_text_value1 : 'custom value',
            'customInvoice2': invoice ? invoice.custom_text_value2 : 'custom value',
        };

        // Add any available payment method links
        @foreach (\App\Models\Gateway::$gatewayTypes as $type)
            @if ($type != GATEWAY_TYPE_TOKEN)
                {!! "keys['" . Utils::toCamelCase(\App\Models\GatewayType::getAliasFromId($type)) . "Link'] = '" . URL::to('/payment/...') . "';" !!}
                {!! "keys['" . Utils::toCamelCase(\App\Models\GatewayType::getAliasFromId($type)) . "Button'] = '" . Form::flatButton('pay_now', '#36c157') . "';" !!}
            @endif
        @endforeach

        var includesPasswordPlaceholder = str.indexOf('$password') != -1;

        for (var key in keys) {
            var val = keys[key];
            var regExp = new RegExp('\\$'+key, 'g');
            str = str.replace(regExp, val);
        }

        if (!includesPasswordPlaceholder){
            var lastSpot = str.lastIndexOf('$password')
            str = str.slice(0, lastSpot) + str.slice(lastSpot).replace('$password', passwordHtml);
        }
        str = str.replace(/\$password/g,'');

        return str;
    }

</script>

<div class="modal fade" id="templateHelpModal" tabindex="-1" role="dialog" aria-labelledby="templateHelpModalLabel" aria-hidden="true" style="z-index:10001">
    <div class="modal-dialog" style="min-width:150px">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="templateHelpModalLabel">{{ trans('texts.template_help_title') }}</h4>
            </div>

            <div class="container" style="width: 100%; padding-bottom: 0px !important">
            <div class="panel panel-default">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <p>{{ trans('texts.company_variables') }}</p>
                        <ul>
                            @foreach([
                                'account',
                                'emailSignature',
                            ] as $field)
                                <li>${{ $field }}</li>
                            @endforeach
                        </ul>
                        <p>{{ trans('texts.client_variables') }}</p>
                        <ul>
                            @foreach([
                                'client',
                                'contact',
                                'firstName',
                                'password',
                                'autoBill',
                            ] as $field)
                                <li>${{ $field }}</li>
                            @endforeach
                        </ul>
                        <p>{{ trans('texts.invoice_variables') }}</p>
                        <ul>
                            @foreach([
                                'invoice',
                                'quote',
                                'amount',
                                'total',
                                'balance',
                                'invoiceDate',
                                'dueDate',
                                'documents',
                            ] as $field)
                                <li>${{ $field }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <p>{{ trans('texts.navigation_variables') }}</p>
                        <ul>
                            @foreach([
                                'viewLink',
                                'viewButton',
                                'paymentLink',
                                'paymentButton',
                                'portalLink',
                                'portalButton',
                            ] as $field)
                                <li>${{ $field }}</li>
                            @endforeach
                            @foreach (\App\Models\Gateway::$gatewayTypes as $type)
                                @if ($account->getGatewayByType($type))
                                    @if ($type != GATEWAY_TYPE_TOKEN)
                                        <li>${{ Utils::toCamelCase(\App\Models\GatewayType::getAliasFromId($type)) }}Link</li>
                                        <li>${{ Utils::toCamelCase(\App\Models\GatewayType::getAliasFromId($type)) }}Button</li>
                                    @endif
                                @endif
                            @endforeach
                        </ul>
                        @if ($account->custom_client_label1 || $account->custom_contact_label1 || $account->custom_invoice_text_label1)
                            <p>{{ trans('texts.custom_variables') }}</p>
                            <ul>
                                @if ($account->custom_client_label1)
                                    <li>$customClient1</li>
                                @endif
                                @if ($account->custom_client_label2)
                                    <li>$customClient2</li>
                                @endif
                                @if ($account->custom_contact_label1)
                                    <li>$customContact1</li>
                                @endif
                                @if ($account->custom_contact_label2)
                                    <li>$customContact2</li>
                                @endif
                                @if ($account->custom_invoice_text_label1)
                                    <li>$customInvoice1</li>
                                @endif
                                @if ($account->custom_invoice_text_label2)
                                    <li>$customInvoice2</li>
                                @endif
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
            </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
            </div>

        </div>
    </div>
</div>
