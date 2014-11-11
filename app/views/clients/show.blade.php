@extends('header')

@section('content') 
	
	
	@if (!$client->trashed())		
	<div class="pull-right">
		{{ Former::open('clients/bulk')->addClass('mainForm') }}
		<div style="display:none">
			{{ Former::text('action') }}
			{{ Former::text('id')->value($client->public_id) }}
		</div>

		{{ DropdownButton::normal(trans('texts.edit_client'),
			  Navigation::links(
			    [
			      [trans('texts.edit_client'), URL::to('clients/' . $client->public_id . '/edit')],
			      [Navigation::DIVIDER],
			      [trans('texts.archive_client'), "javascript:onArchiveClick()"],
			      [trans('texts.delete_client'), "javascript:onDeleteClick()"],
			    ]
			  )
			, ['id'=>'normalDropDown'])->split(); }}

			{{ DropdownButton::primary('Create Invoice', Navigation::links($actionLinks), ['id'=>'primaryDropDown'])->split(); }}
	    {{ Former::close() }}		

	</div>
	@endif

	<h2>{{ $client->getDisplayName() }}</h2>
	@if ($client->last_login > 0)
	<h3 style="margin-top:0px"><small>		
		{{ trans('texts.last_logged_in') }} {{ Utils::timestampToDateTimeString(strtotime($client->last_login)); }}
	</small></h3>
	@endif

	<div class="row">

		<div class="col-md-3">
			<h3>{{ trans('texts.details') }}</h3>
                        <p>{{ $client->getIdNumber() }}</p>
		  	<p>{{ $client->getVatNumber() }}</p>
                        <p>{{ $client->getAddress() }}</p>
		  	<p>{{ $client->getCustomFields() }}</p>
		  	<p>{{ $client->getPhone() }}</p>
		  	<p>{{ $client->getNotes() }}</p>
		  	<p>{{ $client->getIndustry() }}</p>
		  	<p>{{ $client->getWebsite() }}</p>
		  	<p>{{ $client->payment_terms ? trans('texts.payment_terms') . ": Net " . $client->payment_terms : '' }}</p>
		</div>

		<div class="col-md-3">
			<h3>{{ trans('texts.contacts') }}</h3>
		  	@foreach ($client->contacts as $contact)		  	
		  		{{ $contact->getDetails() }}		  	
		  	@endforeach			
		</div>

		<div class="col-md-6">
			<h3>{{ trans('texts.standing') }}
			<table class="table" style="width:300px">
				<tr>
					<td><small>{{ trans('texts.paid_to_date') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($client->paid_to_date, $client->currency_id); }}</td>
				</tr>
				<tr>
					<td><small>{{ trans('texts.balance') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($client->balance, $client->currency_id); }}</td>
				</tr>
				@if ($credit > 0)
				<tr>
					<td><small>{{ trans('texts.credit') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($credit, $client->currency_id); }}</td>
				</tr>
				@endif
			</table>
			</h3>

		</div>
	</div>

	<p>&nbsp;</p>
	
	<ul class="nav nav-tabs nav-justified">
		{{ HTML::tab_link('#activity', trans('texts.activity'), true) }}
		@if (Utils::isPro())
			{{ HTML::tab_link('#quotes', trans('texts.quotes')) }}
		@endif
		{{ HTML::tab_link('#invoices', trans('texts.invoices')) }}
		{{ HTML::tab_link('#payments', trans('texts.payments')) }}			
		{{ HTML::tab_link('#credits', trans('texts.credits')) }}			
	</ul>

	<div class="tab-content">

        <div class="tab-pane active" id="activity">

			{{ Datatable::table()		
		    	->addColumn(
		    		trans('texts.date'),
		    		trans('texts.message'),
		    		trans('texts.balance'),
		    		trans('texts.adjustment'))
		    	->setUrl(url('api/activities/'. $client->public_id))    	
		    	->setOptions('sPaginationType', 'bootstrap')
		    	->setOptions('bFilter', false)
		    	->setOptions('aaSorting', [['0', 'desc']])
		    	->render('datatable') }}

        </div>

    @if (Utils::isPro())
        <div class="tab-pane" id="quotes">

			{{ Datatable::table()		
		    	->addColumn(
	    			trans('texts.quote_number'),
	    			trans('texts.quote_date'),
	    			trans('texts.total'),
	    			trans('texts.due_date'),
	    			trans('texts.status'))
		    	->setUrl(url('api/quotes/'. $client->public_id))    	
		    	->setOptions('sPaginationType', 'bootstrap')
		    	->setOptions('bFilter', false)
		    	->setOptions('aaSorting', [['0', 'desc']])
		    	->render('datatable') }}

        </div>
    @endif

		<div class="tab-pane" id="invoices">

			@if ($hasRecurringInvoices)
				{{ Datatable::table()		
			    	->addColumn(
			    		trans('texts.frequency_id'),
			    		trans('texts.start_date'),
			    		trans('texts.end_date'),
			    		trans('texts.invoice_total'))			    		
			    	->setUrl(url('api/recurring_invoices/' . $client->public_id))    	
			    	->setOptions('sPaginationType', 'bootstrap')
			    	->setOptions('bFilter', false)
			    	->setOptions('aaSorting', [['0', 'asc']])
			    	->render('datatable') }}
			@endif

			{{ Datatable::table()		
		    	->addColumn(
		    			trans('texts.invoice_number'),
		    			trans('texts.invoice_date'),
		    			trans('texts.invoice_total'),
		    			trans('texts.balance_due'),
		    			trans('texts.due_date'),
		    			trans('texts.status'))
		    	->setUrl(url('api/invoices/' . $client->public_id))    	
		    	->setOptions('sPaginationType', 'bootstrap')
		    	->setOptions('bFilter', false)
		    	->setOptions('aaSorting', [['0', 'asc']])
		    	->render('datatable') }}
            
        </div>
        <div class="tab-pane" id="payments">

	    	{{ Datatable::table()		
						->addColumn(
			    			trans('texts.invoice'),
			    			trans('texts.transaction_reference'),				
			    			trans('texts.method'),		    			
			    			trans('texts.payment_amount'),
			    			trans('texts.payment_date'))
				->setUrl(url('api/payments/' . $client->public_id))    	
				->setOptions('sPaginationType', 'bootstrap')
				->setOptions('bFilter', false)
				->setOptions('aaSorting', [['0', 'asc']])
				->render('datatable') }}
            
        </div>
        <div class="tab-pane" id="credits">

	    	{{ Datatable::table()		
						->addColumn(
								trans('texts.credit_amount'),
								trans('texts.credit_balance'),
								trans('texts.credit_date'),
								trans('texts.private_notes'))
				->setUrl(url('api/credits/' . $client->public_id))    	
				->setOptions('sPaginationType', 'bootstrap')
				->setOptions('bFilter', false)
				->setOptions('aaSorting', [['0', 'asc']])
				->render('datatable') }}
            
        </div>
    </div>
	
	<script type="text/javascript">

	$(function() {
		$('#normalDropDown > button:first').click(function() {
			window.location = '{{ URL::to('clients/' . $client->public_id . '/edit') }}';
		});
		$('#primaryDropDown > button:first').click(function() {
			window.location = '{{ URL::to('invoices/create/' . $client->public_id ) }}';
		});
	});

	function onArchiveClick() {
		$('#action').val('archive');
		$('.mainForm').submit();
	}

	function onDeleteClick() {
		if (confirm("{{ trans('texts.are_you_sure') }}")) {
			$('#action').val('delete');
			$('.mainForm').submit();
		}		
	}

	</script>

@stop