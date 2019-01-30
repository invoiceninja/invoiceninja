<tr>
    <td>{{ trans('texts.vendor') }}</td>
    <td>{{ trans('texts.client') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.expense_date') }}</td>
    <td>{{ trans('texts.amount') }}</td>
    <td>{{ trans('texts.tax') }}</td>
    <td>{{ trans('texts.currency') }}</td>
    <td>{{ trans('texts.category') }}</td>
    <td>{{ trans('texts.status') }}</td>
    <td>{{ trans('texts.public_notes') }}</td>
    <td>{{ trans('texts.private_notes') }}</td>
    <td>{{ trans('texts.payment_type') }}</td>
    <td>{{ trans('texts.payment_date') }}</td>
    <td>{{ trans('texts.transaction_reference') }}</td>
</tr>

@foreach ($expenses as $expense)
    <tr>
        <td>{{ $expense->vendor ? $expense->vendor->getDisplayName() : '' }}</td>
        <td>{{ $expense->client ? $expense->client->getDisplayName() : '' }}</td>
        @if ($multiUser)
            <td>{{ $expense->user->getDisplayName() }}</td>
        @endif
        <td>{{ $expense->expense_date }}</td>
        <td>{{ $expense->present()->amount }}</td>
        <td>{{ $expense->present()->taxAmount }}</td>
        <td>{{ $expense->present()->currencyCode }}</td>
        <td>{{ $expense->present()->category }}</td>
        <td>{{ $expense->statusLabel() }}</td>
        <td>{{ $expense->public_notes }}</td>
        <td>{{ $expense->private_notes }}</td>
        <td>{{ $expense->present()->payment_type }}</td>
        <td>{{ $expense->payment_date }}</td>
        <td>{{ $expense->transaction_reference }}</td>
    </tr>
@endforeach
