<tr>
    <td>{{ trans('texts.client') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.first_name') }}</td>
    <td>{{ trans('texts.last_name') }}</td>
    <td>{{ trans('texts.email') }}</td>
    <td>{{ trans('texts.phone') }}</td>
</tr>

@foreach ($vendor_contacts as $contact)
    @if (!$vendor_contact->vendor->is_deleted)
        <tr>
            <td>{{ $vendor_contact->vendor->getDisplayName() }}</td>
            @if ($multiUser)
                <td>{{ $vendor_contact->user->getDisplayName() }}</td>
            @endif
            <td>{{ $vendor_contact->first_name }}</td>
            <td>{{ $vendor_contact->last_name }}</td>
            <td>{{ $vendor_contact->email }}</td>
            <td>{{ $vendor_contact->phone }}</td>
        </tr>
    @endif
@endforeach

<tr><td></td></tr>
