<tr>
    <td>{{ trans('texts.client') }}</td>
    <td>{{ trans('texts.email') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.frequency') }}</td>
    <td>{{ trans('texts.balance') }}</td>
    <td>{{ trans('texts.amount') }}</td>
    <td>{{ trans('texts.po_number') }}</td>
    <td>{{ trans('texts.status') }}</td>
    @if ($account->custom_invoice_label1)
        <td>{{ $account->custom_invoice_label1 }}</td>
    @endif
    @if ($account->custom_invoice_label2)
        <td>{{ $account->custom_invoice_label2 }}</td>
    @endif
    @if ($account->custom_invoice_text_label1)
        <td>{{ $account->custom_invoice_text_label1 }}</td>
    @endif
    @if ($account->custom_invoice_text_label2)
        <td>{{ $account->custom_invoice_text_label2 }}</td>
    @endif
</tr>

@foreach ($recurringInvoices as $invoice)
    @if (!$invoice->client->is_deleted)
        <tr>
            <td>{{ $invoice->present()->client }}</td>
            <td>{{ $invoice->present()->email }}</td>
            @if ($multiUser)
                <td>{{ $invoice->present()->user }}</td>
            @endif
            <td>{{ $invoice->present()->frequency }}</td>
            <td>{{ $account->formatMoney($invoice->balance, $invoice->client) }}</td>
            <td>{{ $account->formatMoney($invoice->amount, $invoice->client) }}</td>
            <td>{{ $invoice->po_number }}</td>
            <td>{{ $invoice->present()->status }}</td>
            @if ($account->custom_invoice_label1)
                <td>{{ $invoice->custom_value1 }}</td>
            @endif
            @if ($account->custom_invoice_label2)
                <td>{{ $invoice->custom_value2 }}</td>
            @endif
            @if ($account->custom_invoice_label1)
                <td>{{ $invoice->custom_text_value1 }}</td>
            @endif
            @if ($account->custom_invoice_label2)
                <td>{{ $invoice->custom_text_value2 }}</td>
            @endif
        </tr>
    @endif
@endforeach
