<tr>
    <td>{{ trans('texts.name') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.address1') }}</td>
    <td>{{ trans('texts.address2') }}</td>
    <td>{{ trans('texts.city') }}</td>
    <td>{{ trans('texts.state') }}</td>
    <td>{{ trans('texts.postal_code') }}</td>
    <td>{{ trans('texts.country') }}</td>
</tr>

@foreach ($vendors as $vendor)
    <tr>
        <td>{{ $vendor->getDisplayName() }}</td>
        @if ($multiUser)
            <td>{{ $vendor->user->getDisplayName() }}</td>
        @endif
        <td>{{ $vendor->address1 }}</td>
        <td>{{ $vendor->address2 }}</td>
        <td>{{ $vendor->city }}</td>
        <td>{{ $vendor->state }}</td>
        <td>{{ $vendor->postal_code }}</td>
        <td>{{ $vendor->present()->country }}</td>
    </tr>
@endforeach
