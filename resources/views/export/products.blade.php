<tr>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.product') }}</td>
    <td>{{ trans('texts.notes') }}</td>
    <td>{{ trans('texts.cost') }}</td>
    @if ($account->customLabel('product1'))
        <td>{{ $account->present()->customLabel('product1') }}</td>
    @endif
    @if ($account->customLabel('product2'))
        <td>{{ $account->present()->customLabel('product2') }}</td>
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
        @if ($account->customLabel('product1'))

        @endif
        @if ($account->customLabel('product1'))
            <td>{{ $product->custom_value1 }}</td>
        @endif
        @if ($account->customLabel('product2'))
            <td>{{ $product->custom_value2 }}</td>
        @endif
    </tr>
@endforeach
