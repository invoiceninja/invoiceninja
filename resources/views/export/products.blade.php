<tr>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.product') }}</td>
    <td>{{ trans('texts.notes') }}</td>
    <td>{{ trans('texts.cost') }}</td>
    @if ($account->custom_invoice_item_label1)
        <td>{{ $account->present()->customProductLabel1 }}</td>
    @endif
    @if ($account->custom_invoice_item_label2)
        <td>{{ $account->present()->customProductLabel2 }}</td>
    @endif
</tr>

@foreach ($products as $product)
    <tr>
        @if ($multiUser)
            <td>{{ $product->present()->user }}</td>
        @endif
        <td>{{ $product->product_key }}</td>
        <td>{{ $product->notes }}</td>
        <td>{{ $product->cost }}</td>
        @if ($account->custom_invoice_item_label1)

        @endif
        @if ($account->custom_invoice_item_label1)
            <td>{{ $product->custom_value1 }}</td>
        @endif
        @if ($account->custom_invoice_item_label2)
            <td>{{ $product->custom_value2 }}</td>
        @endif
    </tr>
@endforeach
