<html>

    <tr>
        <td>{{ $title }}</td>
    </tr>
    <tr><td></td></tr>

    @if (isset($clients) && $clients && count($clients))
        <tr><td>{{ strtoupper(trans('texts.clients')) }}</td></tr>
        @include('export.clients')
    @endif

    @if (isset($contacts) && $contacts && count($contacts))
        <tr><td>{{ strtoupper(trans('texts.contacts')) }}</td></tr>
        @include('export.contacts')
    @endif

    @if (isset($credits) && $credits && count($credits))
        <tr><td>{{ strtoupper(trans('texts.credits')) }}</td></tr>
        @include('export.credits')
    @endif

    @if (isset($tasks) && $tasks && count($tasks))
        <tr><td>{{ strtoupper(trans('texts.tasks')) }}</td></tr>
        @include('export.tasks')
    @endif

    @if (isset($invoices) && $invoices && count($invoices))
        <tr><td>{{ strtoupper(trans('texts.invoices')) }}</td></tr>
        @include('export.invoices')
    @endif

    @if (isset($quotes) && $quotes && count($quotes))
        <tr><td>{{ strtoupper(trans('texts.quotes')) }}</td></tr>
        @include('export.invoices', ['entityType' => ENTITY_QUOTE])
    @endif

    @if (isset($recurringInvoices) && $recurringInvoices && count($recurringInvoices))
        <tr><td>{{ strtoupper(trans('texts.recurring_invoices')) }}</td></tr>
        @include('export.recurring_invoices', ['entityType' => ENTITY_RECURRING_INVOICE])
    @endif

    @if (isset($payments) && $payments && count($payments))
        <tr><td>{{ strtoupper(trans('texts.payments')) }}</td></tr>
        @include('export.payments')
    @endif

</html>