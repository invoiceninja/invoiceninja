@extends('header')

@section('content')

<div class="row">
  <div class="col-md-4">  
    <div class="panel panel-default">
      <div class="panel-body">
        <img src="{{ asset('images/totalincome.png') }}" class="in-image"/>  
        <div class="in-bold">
          {{ $totalIncome }}
        </div>
        <div class="in-thin">
          in total revenue
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
          {{ Utils::pluralize('billed client', $billedClients) }}
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
          {{ Utils::pluralize('invoice', $invoicesSent) }} sent          
        </div>
      </div>
    </div>
  </div>
</div>


<p>&nbsp;</p>

<div class="row">
  <div class="col-md-6">  
    <div class="panel panel-default" style="min-height:320px">
      <div class="panel-heading" style="background-color:#2299c0">
        <h3 class="panel-title in-bold-white">
          <i class="glyphicon glyphicon-exclamation-sign"></i> Notifications
        </h3>
      </div>
      <ul class="panel-body list-group">
      @foreach ($activities as $activity)
        <li class="list-group-item">
          <span style="color:#888;font-style:italic">{{ Utils::timestampToDateString(strtotime($activity->created_at)) }}:</span>
          {{ Utils::decodeActivity($activity->message) }}
        </li>
      @endforeach
      </ul>
    </div>  
  </div>
  <div class="col-md-6">  
    <div class="panel panel-default" style="min-height:320px">
      <div class="panel-heading" style="background-color:#e37329">
        <h3 class="panel-title in-bold-white">
          <i class="glyphicon glyphicon-time"></i> Invoices Past Due
        </h3>
      </div>
      <div class="panel-body">
        <table class="table">
          <thead>
            <th>Invoice #</th>
            <th>Client</th>
            <th>Due date</th>
            <th>Balance due</th>
          </thead>
          <tbody>
            @foreach ($pastDue as $invoice)
              <tr>
                <td>{{ $invoice->getLink() }}</td>
                <td>{{ $invoice->client->getDisplayName() }}</td>
                <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>
                <td>{{ Utils::formatMoney($invoice->balance, $invoice->client->currency_id) }}</td>
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
    <div class="panel panel-default" style="min-height:320px">
      <div class="panel-heading">
        <h3 class="panel-title">
          <i class="glyphicon glyphicon-time"></i> Upcoming invoices
        </h3>
      </div>
      <div class="panel-body">
        <table class="table">
          <thead>
            <th>Invoice #</th>
            <th>Client</th>
            <th>Due date</th>
            <th>Balance due</th>
          </thead>
          <tbody>
            @foreach ($upcoming as $invoice)
              <tr>
                <td>{{ $invoice->getLink() }}</td>
                <td>{{ $invoice->client->getDisplayName() }}</td>
                <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>
                <td>{{ Utils::formatMoney($invoice->balance, $invoice->client->currency_id) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="col-md-6 active-clients">      
      <div class="in-bold in-white" style="font-size:42px">{{ $activeClients }}</div>
      <div class="in-thin in-white">{{ Utils::pluralize('active client', $activeClients) }}</div>
    </div>
    <div class="col-md-6 average-invoice">  
      <div><b>Average invoice</b></div>
      <div class="in-bold in-white" style="font-size:42px">{{ $invoiceAvg }}</div>
    </div>
  </div> 
</div>

@stop