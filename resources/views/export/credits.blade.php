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
    <tr>
        <td>{{ $credit->client->getDisplayName() }}</td>
        @if ($multiUser)
            <td>{{ $credit->user->getDisplayName() }}</td>
        @endif
        <td>{{ $credit->present()->amount }}</td>
        <td>{{ $credit->present()->balance }}</td>
        <td>{{ $credit->present()->credit_date }}</td>
    </tr>
@endforeach

<tr><td></td></tr>