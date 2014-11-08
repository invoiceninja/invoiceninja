@extends('header')

<link href="{{ asset('css/customCss.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>

<script src="{{ asset('js/jquery.min.js') }}" type="text/javascript"></script>  

<script type="text/javascript">
	
	$(document).ready(
		function() {
	    $("#informationBox").niceScroll();
	    //$("#upComingDataScrolls").niceScroll();
	 });
</script>



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
    <div class="panel-default dashboard alertBox">
      <div>
      <div class="panel-heading" style="background-color:#FFFFFF;padding-bottom: 20px">
        <h3 class="panel-title in-bold-black">
          <span class="img-wrap shiftLeft alertIcon" >
          	<img src="{{ asset('images/alert_icon.png') }}">
          	</span> 
          	<span>{{ trans('texts.alert') }}</span> 
          <span class="viewAll orange">{{ trans('texts.view_all') }}</span>
        </h3>
      </div>
      
      <div id="informationBox">
      	
	      <ul class="panel-body list-group">
	     
		     {{$oddLoop = false;}}
		      @foreach ($activities as $activity)
		     	@if($oddLoop=!$oddLoop)
		        <li class="list-group-item oddData" style="border: 0px solid #ddd;">
		          <span style="color:#888;font-style:italic">{{ Utils::timestampToDateString(strtotime($activity->created_at)) }}:</span>
		          {{ Utils::decodeActivity($activity->message) }} 
		        </li>
		        @else
		        <li class="list-group-item" style="border: 0px solid #ddd;">
		          <span style="color:#888;font-style:italic">{{ Utils::timestampToDateString(strtotime($activity->created_at)) }}:</span>
		          {{ Utils::decodeActivity($activity->message) }} 
		        </li>
		        @endif
		      @endforeach
	      </ul>
	
      </div>
      </div>
    </div>  
  </div>
  <div class="col-md-6">  
    <div class="panel-default dashboard upComingInvBox">
      <div class="panel-heading" style="background-color:#FFFFFF;padding-bottom: 0px;">
        <h3 class="panel-title in-bold-black">
        	<span class="img-wrap shiftLeft upComingInvIcon" >
        		<img src="{{ asset('images/upcoming_invoice_icon.png') }}">
        	</span> 
        	<span>{{ trans('texts.upcoming_invoices') }}</span> 
         	<span class="viewAll green">{{ trans('texts.view_all') }}</span>
        </h3>
      </div>
      
      <table class="table table-striped" style="margin-bottom: 0px !important;">
          <thead>
            <th class="tableHead">{{ trans('texts.client') }}</th>
            <th class="tableHead">{{ trans('texts.invoice_number_short') }}</th>
            <th class="tableHead">{{ trans('texts.balance') }}</th>
            <th class="tableHead">{{ trans('texts.due_date') }}</th>
          </thead>
      </table>
      
      <div id="upComingDataScrolls">
      <div class="panel-body">
        <table class="table table-striped">
	          <tbody>
	          	 
	            @foreach ($upcoming as $invoice)
	              <tr>
	                <td class="tableTDBorder">{{ $invoice->client->getDisplayName() }}</td>
	                <td class="tableTDBorder">{{ $invoice->getLink() }}</td>
	                <td class="tableTDBorder">{{ Utils::formatMoney($invoice->balance, $invoice->client->currency_id) }}</td>
	                <td class="tableTDBorder">{{ Utils::fromSqlDate($invoice->due_date) }}</td>
	              </tr>
	            @endforeach
	          </tbody>
        </table>
      </div>
      </div>
    </div>  
  </div>
</div>

<p>&nbsp;</p>

<div class="row" style="background-color: white;">
	<p>&nbsp;</p>
	
	<div class="col-md-6">  
    <div class="panel-default dashboard postDueInvBox">
      <div class="panel-heading" style="background-color:#FFFFFF;padding-bottom: 0px;border-bottom: 0px solid transparent;">
        <h3 class="panel-title in-bold-black">
        	<span class="img-wrap shiftLeft postDueInvIcon" >
        		<img src="{{ asset('images/past_due_invoice_icon.png') }}">
        	</span> 
        	<span>{{ trans('texts.invoices_past_due') }}</span> 
         	<span class="viewAll blue">{{ trans('texts.view_all') }}</span>
        </h3>
      </div>
      
      <table class="table table-striped" style="margin-bottom: 0px !important;">
          <thead>
            <th class="tableHead">{{ trans('texts.client') }}</th>
            <th class="tableHead">{{ trans('texts.invoice_number_short') }}</th>
            <th class="tableHead">{{ trans('texts.balance') }}</th>
            <th class="tableHead">{{ trans('texts.due_date') }}</th>
          </thead>
      </table>
      
      <div id="upComingDataScrolls">
      <div class="panel-body">
        <table class="table table-striped">
	          <tbody>
	          	  <tr>
	                <td class="tableTDBorder">ads</td>
	                <td class="tableTDBorder">sdfds</td>
	                <td class="tableTDBorder">sdf</td>
	                <td class="tableTDBorder">sdfs</td>
	              </tr>
	             <tr>
	                <td class="tableTDBorder">ads</td>
	                <td class="tableTDBorder">sdfds</td>
	                <td class="tableTDBorder">sdf</td>
	                <td class="tableTDBorder">sdfs</td>
	              </tr>
	              <tr>
	                <td class="tableTDBorder">ads</td>
	                <td class="tableTDBorder">sdfds</td>
	                <td class="tableTDBorder">sdf</td>
	                <td class="tableTDBorder">sdfs</td>
	              </tr>
	              <tr>
	                <td class="tableTDBorder">ads</td>
	                <td class="tableTDBorder">sdfds</td>
	                <td class="tableTDBorder">sdf</td>
	                <td class="tableTDBorder">sdfs</td>
	              </tr>
	              <tr>
	                <td class="tableTDBorder">ads</td>
	                <td class="tableTDBorder">sdfds</td>
	                <td class="tableTDBorder">sdf</td>
	                <td class="tableTDBorder">sdfs</td>
	              </tr>
	              <tr>
	                <td class="tableTDBorder">ads</td>
	                <td class="tableTDBorder">sdfds</td>
	                <td class="tableTDBorder">sdf</td>
	                <td class="tableTDBorder">sdfs</td>
	              </tr>
	              <tr>
	                <td class="tableTDBorder">ads</td>
	                <td class="tableTDBorder">sdfds</td>
	                <td class="tableTDBorder">sdf</td>
	                <td class="tableTDBorder">sdfs</td>
	              </tr>
	              <tr>
	                <td class="tableTDBorder">ads</td>
	                <td class="tableTDBorder">sdfds</td>
	                <td class="tableTDBorder">sdf</td>
	                <td class="tableTDBorder">sdfs</td>
	              </tr>
	              <tr>
	                <td class="tableTDBorder">ads</td>
	                <td class="tableTDBorder">sdfds</td>
	                <td class="tableTDBorder">sdf</td>
	                <td class="tableTDBorder">sdfs</td>
	              </tr>
	            @foreach ($pastDue as $invoice)
	              <tr>
	                <td class="tableTDBorder">{{ $invoice->client->getDisplayName() }}</td>
	                <td class="tableTDBorder">{{ $invoice->getLink() }}</td>
	                <td class="tableTDBorder">{{ Utils::formatMoney($invoice->balance, $invoice->client->currency_id) }}</td>
	                <td class="tableTDBorder">{{ Utils::fromSqlDate($invoice->due_date) }}</td>
	              </tr>
	            @endforeach
	          </tbody>
        </table>
      </div>
      </div>
    </div>  
  </div>
	
  	<div class="col-md-3">
  		<div class="average-invoice"  style="background-color: #FFFFFF;">
  			<span class="img-wrap col-md-offset-0"><img src="{{ asset('images/total_client_icon.png') }}"></span> 
  			<div class="in-bold in-bold-black" style="font-size:16px;"><p style="margin-top: 10px;">{{ trans('texts.total_active_client') }}</p></div>
  			<div class="in-bold green" style="font-size:50px;">{{ $activeClients }}</div>
	      <!--  <div class="in-thin in-white green" style="font-size:42px;">{{ Utils::pluralize('active_client', $activeClients) }}</div> -->
  		</div>
	   <!-- <div class="active-clients">      
	      <div class="in-bold in-white" style="font-size:42px">{{ $activeClients }}</div>
	      <div class="in-thin in-white">{{ Utils::pluralize('active_client', $activeClients) }}</div>
	   </div> -->
     </div>
    <div class="col-md-3">
	    <div class="average-invoice" style="background-color: #FFFFFF;">  
	      <span class="img-wrap col-md-offset-0"><img src="{{ asset('images/avgl_invoice_icon.png') }}"></span> 
	      <div class="in-bold in-bold-black" style="font-size:16px;"><p style="margin-top: 10px;">{{ trans('texts.average_invoice') }}</p></div>
	      <div class="in-bold green" style="font-size:42px">{{ $invoiceAvg }}</div>
	    </div>
  	</div> 
</div>

@stop