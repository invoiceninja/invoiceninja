<tr>
    <td>{{ trans('texts.name') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.amount') }}</td>
    <td>{{ trans('texts.balance') }}</td>
    <td>{{ trans('texts.credit_date') }}</td>
</tr>

@foreach ($credits as $credit)
    @if (!$credit->client->is_deleted)
        <tr>
            <td>{{ $credit->client->getDisplayName() }}</td>
            @if ($multiUser)
                <td>{{ $credit->user->getDisplayName() }}</td>
            @endif
            <td>{{ $account->formatMoney($credit->amount, $credit->client) }}</td>
            <td>{{ $account->formatMoney($credit->balance, $credit->client) }}</td>
            <td>{{ $credit->present()->credit_date }}</td>
        </tr>
    @endif
@endforeach

<tr><td></td></tr>