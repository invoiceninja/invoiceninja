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
    @if (!$contact->vendor->is_deleted)
        <tr>
            <td>{{ $contact->vendor->getDisplayName() }}</td>
            @if ($multiUser)
                <td>{{ $contact->user->getDisplayName() }}</td>
            @endif
            <td>{{ $contact->first_name }}</td>
            <td>{{ $contact->last_name }}</td>
            <td>{{ $contact->email }}</td>
            <td>{{ $contact->phone }}</td>
        </tr>
    @endif
@endforeach
