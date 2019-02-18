<tr>
    <td>{{ trans('texts.client') }}</td>
    <td>{{ trans('texts.email') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans(isset($entityType) && $entityType == ENTITY_QUOTE ? 'texts.quote_number' : 'texts.invoice_number') }}</td>
    <td>{{ trans('texts.amount') }}</td>
    <td>{{ trans('texts.paid') }}</td>
    <td>{{ trans('texts.po_number') }}</td>
    <td>{{ trans('texts.status') }}</td>
    <td>{{ trans(isset($entityType) && $entityType == ENTITY_QUOTE ? 'texts.quote_date' : 'texts.invoice_date') }}</td>
    <td>{{ trans('texts.due_date') }}</td>
    <td>{{ trans('texts.discount') }}</td>
    @if (empty($entityType))
        <td>{{ trans('texts.partial') }}</td>
        <td>{{ trans('texts.partial_due_date') }}</td>
    @endif
    <td>{{ trans('texts.public_notes') }}</td>
    <td>{{ trans('texts.private_notes') }}</td>
    @if ($account->customLabel('invoice1'))
        <td>{{ $account->present()->customLabel('invoice1') }}</td>
    @endif
    @if ($account->customLabel('invoice2'))
        <td>{{ $account->present()->customLabel('invoice2') }}</td>
    @endif
    @if ($account->customLabel('invoice_text1'))
        <td>{{ $account->present()->customLabel('invoice_text1') }}</td>
    @endif
    @if ($account->customLabel('invoice_text2'))
        <td>{{ $account->present()->customLabel('invoice_text2') }}</td>
    @endif
    <td>{{ trans('texts.item_product') }}</td>
    <td>{{ trans('texts.item_notes') }}</td>
    @if ($account->customLabel('product1'))
        <td>{{ $account->present()->customLabel('product1') }}</td>
    @endif
    @if ($account->customLabel('product2'))
        <td>{{ $account->present()->customLabel('product2') }}</td>
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

@foreach ($invoices as $invoice)
    @if (!$invoice->client->is_deleted)
        @foreach ($invoice->invoice_items as $item)
            <tr>
                <td>{{ $invoice->present()->client }}</td>
                <td>{{ $invoice->present()->email }}</td>
                @if ($multiUser)
                    <td>{{ $invoice->present()->user }}</td>
                @endif
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ $account->formatMoney($invoice->amount, $invoice->client) }}</td>
                <td>{{ $account->formatMoney($invoice->amount - $invoice->balance, $invoice->client) }}</td>
                <td>{{ $invoice->po_number }}</td>
                <td>{{ $invoice->present()->status }}</td>
                <td>{{ $invoice->invoice_date }}</td>
                <td>{{ $invoice->due_date }}</td>
                <td>{{ $invoice->discount == 0 ? '' :
                    ($invoice->is_amount_discount
                        ? $account->formatMoney($invoice->discount, $invoice->client)
                        : $invoice->discount . '%') }}</td>
                @if (empty($entityType))
                    <td>{{ $invoice->present()->partial }}</td>
                    <td>{{ $invoice->partial_due_date }}</td>
                @endif
                <td>{{ $invoice->public_notes }}</td>
                <td>{{ $invoice->private_notes }}</td>
                @if ($account->customLabel('invoice1'))
                    <td>{{ $invoice->custom_value1 }}</td>
                @endif
                @if ($account->customLabel('invoice2'))
                    <td>{{ $invoice->custom_value2 }}</td>
                @endif
                @if ($account->customLabel('invoice_text1'))
                    <td>{{ $invoice->custom_text_value1 }}</td>
                @endif
                @if ($account->customLabel('invoice_text2'))
                    <td>{{ $invoice->custom_text_value2 }}</td>
                @endif
                <td>{{ $item->product_key }}</td>
                <td>{{ $item->notes }}</td>
                @if ($account->customLabel('product1'))
                    <td>{{ $item->custom_value1 }}</td>
                @endif
                @if ($account->customLabel('product2'))
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
