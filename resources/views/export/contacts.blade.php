<tr>
    <td>{{ trans('texts.client') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.first_name') }}</td>
    <td>{{ trans('texts.last_name') }}</td>
    <td>{{ trans('texts.email') }}</td>
    <td>{{ trans('texts.phone') }}</td>
    @if ($account->custom_contact_label1)
        <td>{{ $account->custom_contact_label1 }}</td>
    @endif
    @if ($account->custom_contact_label2)
        <td>{{ $account->custom_contact_label2 }}</td>
    @endif
</tr>

@foreach ($contacts as $contact)
    @if (!$contact->client->is_deleted)
        <tr>
            <td>{{ $contact->client->getDisplayName() }}</td>
            @if ($multiUser)
                <td>{{ $contact->user->getDisplayName() }}</td>
            @endif
            <td>{{ $contact->first_name }}</td>
            <td>{{ $contact->last_name }}</td>
            <td>{{ $contact->email }}</td>
            <td>{{ $contact->phone }}</td>
            @if ($account->custom_contact_label1)
                <td>{{ $contact->custom_value1 }}</td>
            @endif
            @if ($account->custom_contact_label2)
                <td>{{ $contact->custom_value2 }}</td>
            @endif
        </tr>
    @endif
@endforeach
