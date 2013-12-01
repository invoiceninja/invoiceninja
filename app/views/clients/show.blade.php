@extends('header')

@section('content')
	
	
	<div class="pull-right">
		{{ Button::link(URL::to('clients/' . $client->id . '/edit'), 'Edit Client') }}
		{{ Button::primary_link(URL::to('invoices/create/' . $client->id), 'Create Invoice') }}
	</div>

	<h2>{{ $client->name }}</h2>

	<div class="row">

		<div class="col-md-3">
			<h3>Details</h3>
		  	<p>{{ $client->getAddress() }}</p>
		  	<p>{{ $client->getPhone() }}</p>
		  	<p>{{ $client->getNotes() }}</p>
		</div>

		<div class="col-md-3">
			<h3>Contacts</h3>
		  	@foreach ($client->contacts as $contact)		  	
		  		{{ $contact->getDetails() }}		  	
		  	@endforeach			
		</div>

		<div class="col-md-6">
			<h3>Standing</h3>
			<h3>$0.00 <small>Paid to Date USD</small></h3>	    
			<h3>$0.00 <small>Balance USD</small></h3>
		</div>
	</div>

	<p>&nbsp;</p>
	
	<ul class="nav nav-tabs nav-justified">
		{{ HTML::tab_link('#activity', 'Activity', true) }}
		{{ HTML::tab_link('#invoices', 'Invoices') }}
		{{ HTML::tab_link('#payments', 'Payments') }}			
	</ul>

	<div class="tab-content">

        <div class="tab-pane active" id="activity">

			{{ Datatable::table()		
		    	->addColumn('Date', 'Message')       
		    	->setUrl(url('api/activities/'. $client->id))    	
		    	->setOptions('sPaginationType', 'bootstrap')
		    	->setOptions('bFilter', false)
		    	->render() }}

        </div>

		<div class="tab-pane" id="invoices">

			{{ Datatable::table()		
		    	->addColumn('Invoice Number', 'Amount', 'Date')       
		    	->setUrl(url('api/invoices/' . $client->id))    	
		    	->setOptions('sPaginationType', 'bootstrap')
		    	->setOptions('bFilter', false)
		    	->render() }}
            
        </div>
        <div class="tab-pane" id="payments">


	    	{{ Datatable::table()		
				->addColumn('Invoice Number', 'Amount', 'Date')       
				->setUrl(url('api/payments/' . $client->id))    	
				->setOptions('sPaginationType', 'bootstrap')
				->setOptions('bFilter', false)
				->render() }}
            
        </div>
    </div>
	
	<script type="text/javascript">

	$(function() {

	});

	</script>

@stop