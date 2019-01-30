<script type="text/javascript">

    function renderEmailTemplate(str, invoice, entityType) {
        if (!str) {
            return '';
        }

        if (invoice && invoice.invoice_type_id == {{ INVOICE_TYPE_QUOTE }} || entityType == '{{ ENTITY_QUOTE }}') {
            var viewButton = {!! json_encode(Form::flatButton('view_quote', '#0b4d78')) !!} + '$password';
        } else if (entityType == '{{ ENTITY_PROPOSAL }}') {
            var viewButton = {!! json_encode(Form::flatButton('view_proposal', '#0b4d78')) !!} + '$password';
        } else {
            var viewButton = {!! json_encode(Form::flatButton('view_invoice', '#0b4d78')) !!} + '$password';
        }

        var passwordHtml = {!! $account->isPro() && $account->enable_portal_password && $account->send_portal_password ? json_encode('<br/>' . trans('texts.password') . ': XXXXXXXXX<br/>') : json_encode('') !!};

        @if ($account->isPro())
            var documentsHtml = {!! json_encode(trans('texts.email_documents_header') . '<ul><li><a>' . trans('texts.email_documents_example_1') . '</a></li><li><a>' . trans('texts.email_documents_example_2') . '</a></li></ul>') !!};
        @else
            var documentsHtml = "";
        @endif

        var keys = {
            'footer': {!! json_encode($account->getEmailFooter()) !!},
            'emailSignature': {!! json_encode($account->getEmailFooter()) !!},
            'account': "{{ $account->getDisplayName() }}",
            'dueDate': invoice ? invoice.partial_due_date || invoice.due_date : "{{ $account->formatDate($account->getDateTime()) }}",
            'invoiceDate': invoice ? invoice.invoice_date : "{{ $account->formatDate($account->getDateTime()) }}",
            'client': invoice ? getClientDisplayName(invoice.client) : "{{ trans('texts.client_name') }}",
            'idNumber' : invoice ? invoice.client.id_number : '12345678',
            'vatNumber' : invoice ? invoice.client.vat_number : '12345678',
            'amount': invoice ? formatMoneyInvoice(parseFloat(invoice.partial) || parseFloat(invoice.balance_amount), invoice) : formatMoneyAccount(100, account),
            'balance': invoice ? formatMoneyInvoice(parseFloat(invoice.balance), invoice) : formatMoneyAccount(100, account),
            'total': invoice ? formatMoneyInvoice(parseFloat(invoice.amount), invoice) : formatMoneyAccount(100, account),
            'partial': invoice ? formatMoneyInvoice(parseFloat(invoice.partial), invoice) : formatMoneyAccount(10, account),
            'contact': invoice ? getContactDisplayName(invoice.client.contacts[0]) : 'Contact Name',
            'firstName': invoice ? invoice.client.contacts[0].first_name : 'First Name',
            'invoice': invoice ? invoice.invoice_number : '0001',
            'quote': invoice ? invoice.invoice_number : '0001',
            'number': invoice ? invoice.invoice_number : '0001',
            'password': passwordHtml,
            'poNumber': invoice ? invoice.po_number : '123456',
            'terms': invoice ? invoice.terms : "{{ trans('texts.terms') }}",
            'notes': invoice ? invoice.public_notes: "{{ trans('texts.notes') }}",
            'documents': documentsHtml,
            'viewLink': '{{ link_to('#', auth()->user()->account->getBaseUrl() . '/...') }}$password',
            'viewButton': viewButton,
            'paymentLink': '{{ link_to('#', auth()->user()->account->getBaseUrl() . '/...') }}$password',
            'paymentButton': {!! json_encode(Form::flatButton('pay_now', '#36c157')) !!} + '$password',
            'approveLink': '{{ link_to('#', auth()->user()->account->getBaseUrl() . '/...') }}$password',
            'approveButton': {!! json_encode(Form::flatButton('approve', '#36c157')) !!} + '$password',
            'autoBill': '{{ trans('texts.auto_bill_notification_placeholder') }}',
            'portalLink': "{{ auth()->user()->account->getBaseUrl() . '/...' }}",
            'portalButton': {!! json_encode(Form::flatButton('view_portal', '#36c157')) !!},
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
                {!! "keys['" . Utils::toCamelCase(\App\Models\GatewayType::getAliasFromId($type)) . "Link'] = '" . auth()->user()->account->getBaseUrl() . "/...';" !!}
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
                                'number',
                                'amount',
                                'total',
                                'balance',
                                'partial',
                                'invoiceDate',
                                'dueDate',
                                'poNumber',
                                'terms',
                                'notes',
                                'documents',
                            ] as $field)
                                <li>${{ $field }}</li>
                            @endforeach
                        </ul>
                    </div>
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
                        <p>{{ trans('texts.navigation_variables') }}</p>
                        <ul>
                            @foreach([
                                'viewLink',
                                'viewButton',
                                'paymentLink',
                                'paymentButton',
                                'approveLink',
                                'approveButton',
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
                        @if ($account->customLabel('client1') || $account->customLabel('contact1') || $account->customLabel('invoice_text1'))
                            <p>{{ trans('texts.custom_variables') }}</p>
                            <ul>
                                @if ($account->customLabel('client1'))
                                    <li>$customClient1</li>
                                @endif
                                @if ($account->customLabel('client2'))
                                    <li>$customClient2</li>
                                @endif
                                @if ($account->customLabel('contact1'))
                                    <li>$customContact1</li>
                                @endif
                                @if ($account->customLabel('contact2'))
                                    <li>$customContact2</li>
                                @endif
                                @if ($account->customLabel('invoice_text1'))
                                    <li>$customInvoice1</li>
                                @endif
                                @if ($account->customLabel('invoice_text2'))
                                    <li>$customInvoice2</li>
                                @endif
                            </ul>
                        @endif
                    </div>
                </div><br/>
                <div class="text-muted">
                    {{ trans('texts.amount_variable_help') }}
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
