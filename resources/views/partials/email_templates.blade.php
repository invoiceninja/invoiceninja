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
            'account': "{{ $account->getDisplayName() }}",
            'dueDate': "{{ $account->formatDate($account->getDateTime()) }}",
            'invoiceDate': "{{ $account->formatDate($account->getDateTime()) }}",
            'client': invoice ? getClientDisplayName(invoice.client) : "{{ trans('texts.client_name') }}",
            'amount': invoice ? formatMoneyInvoice(parseFloat(invoice.partial) || parseFloat(invoice.balance_amount), invoice) : formatMoneyAccount(100, account),
            'contact': invoice ? getContactDisplayName(invoice.client.contacts[0]) : 'Contact Name',
            'firstName': invoice ? invoice.client.contacts[0].first_name : 'First Name',
            'invoice': invoice ? invoice.invoice_number : '0001',
            'quote': invoice ? invoice.invoice_number : '0001',
            'password': passwordHtml,
            'documents': documentsHtml,
            'viewLink': '{{ link_to('#', url('/view/...')) }}$password',
            'viewButton': '{!! Form::flatButton('view_invoice', '#0b4d78') !!}$password',
            'paymentLink': '{{ link_to('#', url('/payment/...')) }}$password',
            'paymentButton': '{!! Form::flatButton('pay_now', '#36c157') !!}$password',
            'autoBill': '{{ trans('texts.auto_bill_notification_placeholder') }}',
            'portalLink': "{{ URL::to('/client/portal/...') }}",
            'portalButton': '{!! Form::flatButton('view_portal', '#36c157') !!}',
            'customClient1': invoice ? invoice.client.custom_value1 : 'custom value',
            'customClient2': invoice ? invoice.client.custom_value2 : 'custom value',
            'customInvoice1': invoice ? invoice.custom_value1 : 'custom value',
            'customInvoice2': invoice ? invoice.custom_value2 : 'custom value',
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
