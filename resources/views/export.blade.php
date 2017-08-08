<html>

    <tr>
        <td>{{ $title }}</td>
    </tr>

    @if (isset($clients) && $clients && count($clients))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.clients')) }}</td></tr>
        @include('export.clients')
    @endif

    @if (isset($contacts) && $contacts && count($contacts))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.contacts')) }}</td></tr>
        @include('export.contacts')
    @endif

    @if (isset($credits) && $credits && count($credits))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.credits')) }}</td></tr>
        @include('export.credits')
    @endif

    @if (isset($tasks) && $tasks && count($tasks))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.tasks')) }}</td></tr>
        @include('export.tasks')
    @endif

    @if (isset($invoices) && $invoices && count($invoices))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.invoices')) }}</td></tr>
        @include('export.invoices')
    @endif

    @if (isset($quotes) && $quotes && count($quotes))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.quotes')) }}</td></tr>
        @include('export.invoices', ['invoices' => $quotes, 'entityType' => ENTITY_QUOTE])
    @endif

    @if (isset($recurringInvoices) && $recurringInvoices && count($recurringInvoices))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.recurring_invoices')) }}</td></tr>
        @include('export.recurring_invoices', ['entityType' => ENTITY_RECURRING_INVOICE])
    @endif

    @if (isset($payments) && $payments && count($payments))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.payments')) }}</td></tr>
        @include('export.payments')
    @endif

    @if (isset($products) && $products && count($products))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.products')) }}</td></tr>
        @include('export.products')
    @endif

    @if (isset($expenses) && $expenses && count($expenses))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.expenses')) }}</td></tr>
        @include('export.expenses')
    @endif

    @if (isset($vendors) && $vendors && count($vendors))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.vendors')) }}</td></tr>
        @include('export.vendors')
    @endif

    @if (isset($vendor_contacts) && $vendor_contacts && count($vendor_contacts))
        <tr><td></td></tr>
        <tr><td>{{ strtoupper(trans('texts.vendor_contacts')) }}</td></tr>
        @include('export.vendor_contacts')
    @endif

</html>
