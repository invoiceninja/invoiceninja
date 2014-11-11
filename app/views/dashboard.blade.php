@extends('header')

<link href="{{ asset('css/customCss.css') }}?no_cache={{ NINJA_VERSION }}" rel="stylesheet" type="text/css"/>
<script src="{{ asset('js/jquery.min.js') }}" type="text/javascript"></script> 


<script type="text/javascript">
	
	$(document).ready(function() {
		
	    $("#informationBox").slimScroll();
	    $(".upComingDataScrolls").slimScroll();
	    
	    $( "#monthButton" ).click(function() {
	    	$("#invoiceAvgValue").text('{{$monthValue}}');
  			$("#monthButton").removeClass('greyButton').addClass('blueButton'); 
	  		$("#yearButton").removeClass('blueButton').addClass('greyButton'); 
	  		$("#weekButton").removeClass('blueButton').addClass('greyButton'); 
	  		
		});
		
		$( "#yearButton" ).click(function() {
			$("#invoiceAvgValue").text('{{$yearValue}}');
  			$("#monthButton").removeClass('blueButton').addClass('greyButton'); 
	  		$("#yearButton").removeClass('greyButton').addClass('blueButton'); 
	  		$("#weekButton").removeClass('blueButton').addClass('greyButton'); 
		});
		
		$( "#weekButton" ).click(function() {
			$("#invoiceAvgValue").text('{{$weekValue}}');
  			$("#monthButton").removeClass('blueButton').addClass('greyButton'); 
	  		$("#yearButton").removeClass('blueButton').addClass('greyButton'); 
	  		$("#weekButton").removeClass('greyButton').addClass('blueButton'); 
		});
		
	 });
</script>

<div class="row headerBar">
	<div class="container" style="padding: 3%;">
		<div class="col-md-6" style="margin-top: 2%;">
			<span class="img-wrap" style="float: left;margin-top: 1%;" ><img src="{{ asset('images/account_dashboard_icon.png') }}"></span> 
			<span style="font-weight: bolder;font-size: 20px;"> {{ trans('texts.account_dashboard') }} </span>
		</div>
		<div class="col-md-6">
			<span class="img-wrap" >
				<center style="float: right;">
					{{ HTML::image($account->getLogoPath(), "Logo") }} &nbsp;
				</center><br/>
		</div>
	</div>	
</div>

@section('content')

	<div class="row" style="background-color: #FFFFFF;">
	   <div class="col-md-3">  
	    <div class="panel-default">
	      <div class="panel-body average-invoice" style="background-color: #FFFFFF;">
	        <span class="img-wrap" ><img src="{{ asset('images/avgl_invoice_icon.png') }}"></span> 
	        <div class="black" ><span style="font-size:18px;">{{ trans('texts.total_outstading') }}</span></div>
	      	<div class="black" ><span style="font-size:20px;">{{ trans('texts.caps_invoice') }}</span></div>
	      
	        <div class="green" style="font-size:35px">{{ $totalIncome }}</div>
	         <div class="col-md-offset-0" style="color: #909090;">Across all clients</div>
	      </div>
	    </div>
	  </div>
	<div class="col-md-9">
		<div class="col-md-12" style="text-align: center;padding: 1%;border-left: 1px solid rgb(223, 221, 221);border-bottom: 1px solid rgb(223, 221, 221);"> 
			{{trans('texts.accounts_aging')}}
			
		</div>
		<div>
		  <div class="col-md-3" style="border-left: 1px solid rgb(223, 221, 221);padding-bottom: 1%;">  
		    <div class="panel-default" style="border: 0px solid transparent">
		      <div class="panel-body orangeRing">
		      		<span class="ringText orange" style="margin-top: 50px;font-size: 25px;"> {{$totalThirtyDayInvoice}}</span>
		      		<span class="ringText"> {{trans('texts.0_30_days_old')}} </span>
		      </div>
		    </div>
		  </div>
		  <div class="col-md-3">
		    <div class="panel-default" style="border: 0px solid transparent">
		      <div class="panel-body blueRing">
		      	<span class="ringText blue" style="margin-top: 50px;font-size: 25px;"> {{$totalThirtyToSixtyDay}}</span>
		      	<span class="ringText"> {{trans('texts.31_60_days_old')}} </span>
		      </div>
		    </div>
		  </div>
		  <div class="col-md-3">
		    <div class="panel-default" style="border: 0px solid transparent">
		      <div class="panel-body greenRing">
		      	<span class="ringText green" style="margin-top: 50px;font-size: 25px;"> {{$totalSixtyToNintyDay}}</span>
		      	<span class="ringText"> {{trans('texts.61_90_days_old')}}</span>
		      </div>
		    </div>
		  </div>
		  <div class="col-md-3">  
		    <div class="panel-default" style="border: 0px solid transparent">
		      <div class="panel-body orangeRing">
		      		<span class="ringText orange" style="margin-top: 50px;font-size: 25px;"> {{$totalNintyAndAboveDay}}</span>
		      		<span class="ringText"> {{trans('texts.91_aboue_days_old')}}</span>
		      </div>
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
      
      <div class="upComingDataScrolls">
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

<div class="row" style="background-color: white;border: 1px solid rgb(223, 221, 221);">
	<div >
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
      
      <div class="upComingDataScrolls">
      <div class="panel-body">
        <table class="table table-striped">
	          <tbody>
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
  		<div class="average-invoice activeClient">
  			<span class="img-wrap col-md-offset-0"><img src="{{ asset('images/total_client_icon.png') }}"></span> 
  			<div class="black" style="font-size:16px;"><p style="margin-top: 10px;">{{ trans('texts.total_active_client') }}</p></div>
  			<div class="green" style="font-size:50px;">{{ $activeClients }}</div>
  		</div>
	   
     </div>
    <div class="col-md-3">
	    <div class="average-invoice" style="background-color: #FFFFFF;">  
	      <span class="img-wrap col-md-offset-0"><img src="{{ asset('images/avgl_invoice_icon.png') }}"></span> 
	      <div class="black" style="font-size:16px;"><p style="margin-top: 10px;">{{ trans('texts.average_invoice') }}</p></div>
	      <div class="green" id="invoiceAvgValue" style="font-size:42px">{{ $yearValue }}</div>
	      <div class="col-md-offset-0" style="color: #909090;">Across all clients</div>
	      <div style="margin-top: 25%;">
	      	<span id="monthButton" class="greyButton">Month </span>
	      	<span id="yearButton" class="blueButton">Year </span>
	      	<span id="weekButton" class="greyButton">Week </span>
	      </div>
	    </div>
  	</div> 
  	</div>
</div>

@stop