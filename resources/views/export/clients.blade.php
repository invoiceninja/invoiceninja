<tr>
    <td>{{ trans('texts.name') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.balance') }}</td>
    <td>{{ trans('texts.paid_to_date') }}</td>
    <td>{{ trans('texts.billing_address1') }}</td>
    <td>{{ trans('texts.billing_address2') }}</td>
    <td>{{ trans('texts.billing_city') }}</td>
    <td>{{ trans('texts.billing_state') }}</td>
    <td>{{ trans('texts.billing_postal_code') }}</td>
    <td>{{ trans('texts.billing_country') }}</td>
    <td>{{ trans('texts.shipping_address1') }}</td>
    <td>{{ trans('texts.shipping_address2') }}</td>
    <td>{{ trans('texts.shipping_city') }}</td>
    <td>{{ trans('texts.shipping_state') }}</td>
    <td>{{ trans('texts.shipping_postal_code') }}</td>
    <td>{{ trans('texts.shipping_country') }}</td>
    <td>{{ trans('texts.id_number') }}</td>
    <td>{{ trans('texts.vat_number') }}</td>
    <td>{{ trans('texts.website') }}</td>
    <td>{{ trans('texts.work_phone') }}</td>
    <td>{{ trans('texts.currency') }}</td>
    <td>{{ trans('texts.public_notes') }}</td>
    <td>{{ trans('texts.private_notes') }}</td>
    @if ($account->custom_client_label1)
        <td>{{ $account->custom_client_label1 }}</td>
    @endif
    @if ($account->custom_client_label2)
        <td>{{ $account->custom_client_label2 }}</td>
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

@foreach ($clients as $client)
    <tr>
        <td>{{ $client->getDisplayName() }}</td>
        @if ($multiUser)
            <td>{{ $client->user->getDisplayName() }}</td>
        @endif
        <td>{{ $account->formatMoney($client->balance, $client) }}</td>
        <td>{{ $account->formatMoney($client->paid_to_date, $client) }}</td>
        <td>{{ $client->address1 }}</td>
        <td>{{ $client->address2 }}</td>
        <td>{{ $client->city }}</td>
        <td>{{ $client->state }}</td>
        <td>{{ $client->postal_code }}</td>
        <td>{{ $client->present()->country }}</td>
        <td>{{ $client->shipping_address1 }}</td>
        <td>{{ $client->shipping_address2 }}</td>
        <td>{{ $client->shipping_city }}</td>
        <td>{{ $client->shipping_state }}</td>
        <td>{{ $client->shipping_postal_code }}</td>
        <td>{{ $client->present()->shipping_country }}</td>
        <td>{{ $client->id_number }}</td>
        <td>{{ $client->vat_number }}</td>
        <td>{{ $client->website }}</td>
        <td>{{ $client->work_phone }}</td>
        <td>{{ $client->currency ? $client->currency->code : '' }}</td>
        <td>{{ $client->public_notes }}</td>
        <td>{{ $client->private_notes }}</td>
        @if ($account->custom_client_label1)
            <td>{{ $client->custom_value1 }}</td>
        @endif
        @if ($account->custom_client_label2)
            <td>{{ $client->custom_value2 }}</td>
        @endif
        <td>{{ $client->contacts[0]->first_name }}</td>
        <td>{{ $client->contacts[0]->last_name }}</td>
        <td>{{ $client->contacts[0]->email }}</td>
        <td>{{ $client->contacts[0]->phone }}</td>
        @if ($account->custom_contact_label1)
            <td>{{ $client->contacts[0]->custom_value1 }}</td>
        @endif
        @if ($account->custom_contact_label2)
            <td>{{ $client->contacts[0]->custom_value2 }}</td>
        @endif
    </tr>
@endforeach
