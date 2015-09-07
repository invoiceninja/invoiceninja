@extends('header')

@section('content')

<div class="row">
    <div class="col-md-4">  
        <div class="panel panel-default">
            <div class="panel-body">
                <img src="{{ asset('images/totalinvoices.png') }}" class="in-image"/>  
                <div class="in-thin">
                    {{ trans('texts.total_revenue') }}
                </div>
                <div class="in-bold">
                    @if (count($paidToDate))
                        @foreach ($paidToDate as $item)
                            {{ Utils::formatMoney($item->value, $item->currency_id) }}<br/>
                        @endforeach
                    @else
                        {{ Utils::formatMoney(0) }}
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-body">
                <img src="{{ asset('images/clients.png') }}" class="in-image"/>  
                <div class="in-thin">
                    {{ trans('texts.average_invoice') }}                    
                </div>
                <div class="in-bold">
                    @if (count($averageInvoice))
                        @foreach ($averageInvoice as $item)
                            {{ Utils::formatMoney($item->invoice_avg, $item->currency_id) }}<br/>
                        @endforeach
                    @else
                        {{ Utils::formatMoney(0) }}
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-body">
                <img src="{{ asset('images/totalincome.png') }}" class="in-image"/>  
                <div class="in-thin">
                    {{ trans('texts.outstanding') }}
                </div>
                <div class="in-bold">
                    @if (count($balances))
                        @foreach ($balances as $item)
                            {{ Utils::formatMoney($item->value, $item->currency_id) }}<br/>
                        @endforeach
                    @else
                        {{ Utils::formatMoney(0) }}
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>


<p>&nbsp;</p>

<div class="row">
    <div class="col-md-6">  
        <div class="panel panel-default dashboard" style="height:320px">
            <div class="panel-heading" style="background-color:#0b4d78 !important">
                <h3 class="panel-title in-bold-white">
                    <i class="glyphicon glyphicon-exclamation-sign"></i> {{ trans('texts.notifications') }}
                    <div class="pull-right" style="font-size:14px;padding-top:4px">
                        {{ trans_choice('texts.invoices_sent', $invoicesSent) }}
                    </div>
                </h3>
            </div>
            <ul class="panel-body list-group" style="height:276px;overflow-y:auto;">
                @foreach ($activities as $activity)
                <li class="list-group-item">
                    <span style="color:#888;font-style:italic">{{ Utils::timestampToDateString(strtotime($activity->created_at)) }}:</span>
                    {!! Utils::decodeActivity($activity->message) !!}
                </li>
                @endforeach
            </ul>
        </div>  
        <div class="panel panel-default dashboard" style="height:320px;">
            <div class="panel-heading" style="margin:0; background-color: #f5f5f5 !important;">
                <h3 class="panel-title" style="color: black !important">
                    <i class="glyphicon glyphicon-ok-sign"></i> {{ trans('texts.recent_payments') }}
                </h3>
            </div>
            <div class="panel-body" style="height:274px;overflow-y:auto;">
                <table class="table table-striped">
                    <thead>
                        <th>{{ trans('texts.invoice_number_short') }}</th>
                        <th>{{ trans('texts.client') }}</th>
                        <th>{{ trans('texts.payment_date') }}</th>
                        <th>{{ trans('texts.amount') }}</th>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                        <tr>
                            <td>{!! \App\Models\Invoice::calcLink($payment) !!}</td>
                            <td>{!! link_to('/clients/'.$payment->client_public_id, trim($payment->client_name) ?: (trim($payment->first_name . ' ' . $payment->last_name) ?: $payment->email)) !!}</td>
                            <td>{{ Utils::fromSqlDate($payment->payment_date) }}</td>
                            <td>{{ Utils::formatMoney($payment->amount, $payment->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
    <div class="col-md-6">  
        <div class="panel panel-default dashboard" style="height:320px">
            <div class="panel-heading" style="background-color:#e37329 !important">
                <h3 class="panel-title in-bold-white">
                    <i class="glyphicon glyphicon-time"></i> {{ trans('texts.invoices_past_due') }}
                </h3>
            </div>
            <div class="panel-body" style="height:274px;overflow-y:auto;">
                <table class="table table-striped">
                    <thead>
                        <th>{{ trans('texts.invoice_number_short') }}</th>
                        <th>{{ trans('texts.client') }}</th>
                        <th>{{ trans('texts.due_date') }}</th>
                        <th>{{ trans('texts.balance_due') }}</th>
                    </thead>
                    <tbody>
                        @foreach ($pastDue as $invoice)
                        <tr>
                            <td>{!! \App\Models\Invoice::calcLink($invoice) !!}</td>
                            <td>{!! link_to('/clients/'.$invoice->client_public_id, trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email)) !!}</td>
                            <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>
                            <td>{{ Utils::formatMoney($invoice->balance, $invoice->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>  
        <div class="panel panel-default dashboard" style="height:320px;">
            <div class="panel-heading" style="margin:0; background-color: #f5f5f5 !important;">
                <h3 class="panel-title" style="color: black !important">
                    <i class="glyphicon glyphicon-time"></i> {{ trans('texts.upcoming_invoices') }}
                </h3>
            </div>
            <div class="panel-body" style="height:274px;overflow-y:auto;">
                <table class="table table-striped">
                    <thead>
                        <th>{{ trans('texts.invoice_number_short') }}</th>
                        <th>{{ trans('texts.client') }}</th>
                        <th>{{ trans('texts.due_date') }}</th>
                        <th>{{ trans('texts.balance_due') }}</th>
                    </thead>
                    <tbody>
                        @foreach ($upcoming as $invoice)
                        <tr>
                            <td>{!! \App\Models\Invoice::calcLink($invoice) !!}</td>
                            <td>{!! link_to('/clients/'.$invoice->client_public_id, trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email)) !!}</td>
                            <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>
                            <td>{{ Utils::formatMoney($invoice->balance, $invoice->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<div class="row">
    <div class="col-md-6">  
    </div>
</div>

@stop

