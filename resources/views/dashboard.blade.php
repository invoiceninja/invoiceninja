@extends('header')

@section('content')

<div class="row">
    <div class="col-md-4">  
        <div class="panel panel-default">
            <div class="panel-body">
                <img src="{{ asset('images/totalincome.png') }}" class="in-image"/>  
                <div class="in-bold">
                    @if (count($paidToDate))
                        @foreach ($paidToDate as $item)
                            {{ Utils::formatMoney($item->value, $item->currency_id) }}<br/>
                        @endforeach
                    @else
                        {{ Utils::formatMoney(0) }}
                    @endif
                </div>
                <div class="in-thin">
                    {{ trans('texts.in_total_revenue') }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-body">
                <img src="{{ asset('images/clients.png') }}" class="in-image"/>  
                <div class="in-bold">
                    {{ $billedClients }}
                </div>
                <div class="in-thin">
                    {{ Utils::pluralize('billed_client', $billedClients) }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-body">
                <img src="{{ asset('images/totalinvoices.png') }}" class="in-image"/>  
                <div class="in-bold">
                    {{ $invoicesSent }}
                </div>
                <div class="in-thin">
                    {{ Utils::pluralize('invoice', $invoicesSent) }} {{ trans('texts.sent') }}
                </div>
            </div>
        </div>
    </div>
</div>


<p>&nbsp;</p>

<div class="row">
    <div class="col-md-6">  
        <div class="panel panel-default dashboard" style="min-height:320px">
            <div class="panel-heading" style="background-color:#0b4d78 !important">
                <h3 class="panel-title in-bold-white">
                    <i class="glyphicon glyphicon-exclamation-sign"></i> {{ trans('texts.notifications') }}
                </h3>
            </div>
            <ul class="panel-body list-group">
                @foreach ($activities as $activity)
                <li class="list-group-item">
                    <span style="color:#888;font-style:italic">{{ Utils::timestampToDateString(strtotime($activity->created_at)) }}:</span>
                    {!! Utils::decodeActivity($activity->message) !!}
                </li>
                @endforeach
            </ul>
        </div>  
    </div>
    <div class="col-md-6">  
        <div class="panel panel-default dashboard" style="min-height:320px">
            <div class="panel-heading" style="background-color:#e37329 !important">
                <h3 class="panel-title in-bold-white">
                    <i class="glyphicon glyphicon-time"></i> {{ trans('texts.invoices_past_due') }}
                </h3>
            </div>
            <div class="panel-body">
                <table class="table table-striped">
                    <thead>
                        <th>{{ trans('texts.invoice_number_short') }}</th>
                        <th>{{ trans('texts.client') }}</th>
                        <th>{{ trans('texts.due_date') }}</th>
                        <th>{{ trans('texts.balance_due') }}</th>
                    </thead>
                    <tbody>
                        @foreach ($pastDue as $invoice)
                        @if (!$invoice->client->trashed())
                        <tr>
                            <td>{!! $invoice->getLink() !!}</td>
                            <td>{{ $invoice->client->getDisplayName() }}</td>
                            <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>
                            <td>{{ Utils::formatMoney($invoice->balance, $invoice->client->currency_id) }}</td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>  
    </div>
</div>

<div class="row">
    <div class="col-md-6">  
        <div class="panel panel-default dashboard" style="min-height:320px;">
            <div class="panel-heading" style="margin:0; background-color: #f5f5f5 !important;">
                <h3 class="panel-title" style="color: black !important">
                    <i class="glyphicon glyphicon-time"></i> {{ trans('texts.upcoming_invoices') }}
                </h3>
            </div>
            <div class="panel-body">
                <table class="table table-striped">
                    <thead>
                        <th>{{ trans('texts.invoice_number_short') }}</th>
                        <th>{{ trans('texts.client') }}</th>
                        <th>{{ trans('texts.due_date') }}</th>
                        <th>{{ trans('texts.balance_due') }}</th>
                    </thead>
                    <tbody>
                        @foreach ($upcoming as $invoice)
                        @if (!$invoice->client->trashed())
                        <tr>
                            <td>{!! $invoice->getLink() !!}</td>
                            <td>{{ $invoice->client->getDisplayName() }}</td>
                            <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>
                            <td>{{ Utils::formatMoney($invoice->balance, $invoice->client->currency_id) }}</td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="active-clients">      
            <div class="in-bold in-white" style="font-size:42px">{{ $activeClients }}</div>
            <div class="in-thin in-white">{{ Utils::pluralize('active_client', $activeClients) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="average-invoice">  
            <div><b>{{ trans('texts.average_invoice') }}</b></div>
            <div class="in-bold in-white" style="font-size:42px">
                @foreach ($averageInvoice as $item)
                {{ Utils::formatMoney($item->invoice_avg, $item->currency_id) }}<br/>
                @endforeach
            </div>
        </div>

    </div> 
</div>

@stop

