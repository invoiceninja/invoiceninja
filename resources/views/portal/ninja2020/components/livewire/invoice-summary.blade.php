<div style="w-full">

<table width="100%">
    <thead>
    </thead>
    <tbody>
        <tr><td>{{ ctrans('texts.invoice')}} #</td><td>{{ $invoice->number }}</td></tr>
        <tr><td>{{ ctrans('texts.due_date')}} #</td><td>{{ $invoice->due_date }}</td></tr>
        <tr><td>{{ ctrans('texts.amount')}} #</td><td>{{ $invoice->amount }}</td></tr>
        <tr><td>{{ ctrans('texts.balance')}} #</td><td>{{ $invoice->balance }}</td></tr>
    </tbody>
</table>

</div>
