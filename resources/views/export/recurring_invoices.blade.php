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
    <td>{{ trans('texts.item_product') }}</td>
    <td>{{ trans('texts.item_notes') }}</td>
    @if ($account->custom_invoice_item_label1)
        <td>{{ $account->custom_invoice_item_label1 }}</td>
    @endif
    @if ($account->custom_invoice_item_label2)
        <td>{{ $account->custom_invoice_item_label2 }}</td>
    @endif
    <td>{{ trans('texts.item_cost') }}</td>
    <td>{{ trans('texts.item_quantity') }}</td>
    @if ($account->invoice_item_taxes)
        <td>{{ trans('texts.item_tax_name') }}</td>
        <td>{{ trans('texts.item_tax_rate') }}</td>
        @if ($account->enable_second_tax_rate)
            <td>{{ trans('texts.item_tax_name') }}</td>
            <td>{{ trans('texts.item_tax_rate') }}</td>
        @endif
    @endif
</tr>

@foreach ($recurringInvoices as $invoice)
    @if (!$invoice->client->is_deleted)
        @foreach ($invoice->invoice_items as $item)
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
                @if ($account->custom_invoice_text_label1)
                    <td>{{ $invoice->custom_text_value1 }}</td>
                @endif
                @if ($account->custom_invoice_text_label2)
                    <td>{{ $invoice->custom_text_value2 }}</td>
                @endif
                <td>{{ $item->product_key }}</td>
                <td>{{ $item->notes }}</td>
                @if ($account->custom_invoice_item_label1)
                    <td>{{ $item->custom_value1 }}</td>
                @endif
                @if ($account->custom_invoice_item_label2)
                    <td>{{ $item->custom_value2 }}</td>
                @endif
                <td>{{ $item->cost }}</td>
                <td>{{ $item->qty }}</td>
                @if ($account->invoice_item_taxes)
                    <td>{{ $item->tax_name1 }}</td>
                    <td>{{ $item->tax_rate1 }}</td>
                    @if ($account->enable_second_tax_rate)
                        <td>{{ $item->tax_name2 }}</td>
                        <td>{{ $item->tax_rate2 }}</td>
                    @endif
                @endif
            </tr>
        @endforeach
    @endif
@endforeach
