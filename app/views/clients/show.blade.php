@extends('header')

@section('content')
	
	
	<div class="pull-right">
		{{ Former::open('clients/bulk')->addClass('mainForm') }}
		<div style="display:none">
			{{ Former::text('action') }}
			{{ Former::text('id')->value($client->id) }}
		</div>

		{{ DropdownButton::normal('Edit Client',
			  Navigation::links(
			    array(
			      array('Edit Client', URL::to('clients/' . $client->id . '/edit')),
			      array(Navigation::DIVIDER),
			      array('Archive Client', "javascript:onArchiveClick()"),
			      array('Delete Client', "javascript:onDeleteClick()"),
			    )
			  )
			, array('id'=>'actionDropDown'))->split(); }}
		{{ Button::primary_link(URL::to('invoices/create/' . $client->id), 'Create Invoice') }}
	    {{ Former::close() }}
		
	</div>

	<h2>{{ $client->name }}</h2>
	@if ($client->last_login > 0)
	<h3 style="margin-top:0px"><small>		
		Last logged in {{ timestampToDateTimeString($client->last_login); }}
	</small></h3>
	@endif

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
		{{ HTML::tab_link('#credits', 'Credits') }}			
	</ul>

	<div class="tab-content">

        <div class="tab-pane active" id="activity">

			{{ Datatable::table()		
		    	->addColumn('Date', 'Message', 'Balance')       
		    	->setUrl(url('api/activities/'. $client->id))    	
		    	->setOptions('sPaginationType', 'bootstrap')
		    	->setOptions('bFilter', false)
		    	->render() }}

        </div>

		<div class="tab-pane" id="invoices">

			{{ Datatable::table()		
		    	->addColumn('Invoice Number', 'Total', 'Amount Due', 'Invoice Date', 'Due Date', 'Status')       
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
        <div class="tab-pane" id="credits">

	    	{{ Datatable::table()		
				->addColumn('Credit Number', 'Amount', 'Credit Date')       
				->setUrl(url('api/credits/' . $client->id))    	
				->setOptions('sPaginationType', 'bootstrap')
				->setOptions('bFilter', false)
				->render() }}
            
        </div>
    </div>
	
	<script type="text/javascript">

	$(function() {
		$('#actionDropDown > button:first').click(function() {
			window.location = '{{ URL::to('clients/' . $client->id . '/edit') }}';
		});
	});

	function onArchiveClick() {
		$('#action').val('archive');
		$('.mainForm').submit();
	}

	function onDeleteClick() {
		if (confirm('Are you sure you want to delete this client?')) {
			$('#action').val('delete');
			$('.mainForm').submit();
		}		
	}

	</script>

@stop