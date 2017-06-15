<tr>
    <td>{{ trans('texts.client') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.invoice_number') }}</td>
    <td>{{ trans('texts.amount') }}</td>
    <td>{{ trans('texts.payment_date') }}</td>
    <td>{{ trans('texts.method') }}</td>
    <td>{{ trans('texts.transaction_reference') }}</td>
    <td>{{ trans('texts.private_notes') }}</td>
</tr>

@foreach ($payments as $payment)
    @if ( ! $payment->client->is_deleted && ! $payment->invoice->is_deleted)
        <tr>
            <td>{{ $payment->present()->client }}</td>
            @if ($multiUser)
                <td>{{ $payment->user->getDisplayName() }}</td>
            @endif
            <td>{{ $payment->invoice->invoice_number }}</td>
            <td>{{ $account->formatMoney($payment->amount, $payment->client) }}</td>
            <td>{{ $payment->present()->payment_date }}</td>
            <td>{{ $payment->present()->method }}</td>
            <td>{{ $payment->transaction_reference }}</td>
            <td>{{ $payment->private_notes }}</td>
        </tr>
    @endif
@endforeach
