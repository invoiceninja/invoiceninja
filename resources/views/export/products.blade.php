<tr>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.product') }}</td>
    <td>{{ trans('texts.notes') }}</td>
    <td>{{ trans('texts.cost') }}</td>
</tr>

@foreach ($products as $product)
    <tr>
        @if ($multiUser)
            <td>{{ $product->present()->user }}</td>
        @endif
        <td>{{ $product->product_key }}</td>
        <td>{{ $product->notes }}</td>
        <td>{{ $product->cost }}</td>
    </tr>
@endforeach
