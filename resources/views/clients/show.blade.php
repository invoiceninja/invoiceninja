@extends('header')

@section('content')


	<div class="pull-right">
		{!! Former::open('clients/bulk')->addClass('mainForm') !!}
		<div style="display:none">
			{!! Former::text('action') !!}
			{!! Former::text('id')->value($client->public_id) !!}
		</div>

        @if ($gatewayLink)
            {!! Button::normal(trans('texts.view_in_stripe'))->asLinkTo($gatewayLink)->withAttributes(['target' => '_blank']) !!}
        @endif

		@if ($client->trashed())
			{!! Button::primary(trans('texts.restore_client'))->withAttributes(['onclick' => 'onRestoreClick()']) !!}
		@else
		    {!! DropdownButton::normal(trans('texts.edit_client'))
                ->withAttributes(['class'=>'normalDropDown'])
                ->withContents([
			      ['label' => trans('texts.archive_client'), 'url' => "javascript:onArchiveClick()"],
			      ['label' => trans('texts.delete_client'), 'url' => "javascript:onDeleteClick()"],
			    ]
			  )->split() !!}

			{!! DropdownButton::primary(trans('texts.new_invoice'))
                    ->withAttributes(['class'=>'primaryDropDown'])
                    ->withContents($actionLinks)->split() !!}
		@endif
	  {!! Former::close() !!}

	</div>


	<h2>{{ $client->getDisplayName() }}</h2>
	@if ($client->last_login > 0)
	<h3 style="margin-top:0px"><small>
		{{ trans('texts.last_logged_in') }} {{ Utils::timestampToDateTimeString(strtotime($client->last_login)) }}
	</small></h3>
	@endif

    <div class="panel panel-default">
    <div class="panel-body">
	<div class="row">

		<div class="col-md-3">
			<h3>{{ trans('texts.details') }}</h3>
            @if ($client->id_number)
                <p><i class="fa fa-id-number" style="width: 20px"></i>{{ trans('texts.id_number').': '.$client->id_number }}</p>
            @endif
            @if ($client->vat_number)
		  	   <p><i class="fa fa-vat-number" style="width: 20px"></i>{{ trans('texts.vat_number').': '.$client->vat_number }}</p>
            @endif

            @if ($client->address1)
                {{ $client->address1 }}<br/>
            @endif
            @if ($client->address2)
                {{ $client->address2 }}<br/>
            @endif
            @if ($client->city)
                {{ $client->city }},
            @endif
            @if ($client->state)
                {{ $client->state }}
            @endif
            @if ($client->postal_code)
                {{ $client->postal_code }}
            @endif
            @if ($client->country)
                <br/>{{ $client->country->name }}
            @endif

            @if ($client->account->custom_client_label1 && $client->custom_value1)
                {{ $client->account->custom_client_label1 . ': ' . $client->custom_value1 }}<br/>
            @endif
            @if ($client->account->custom_client_label2 && $client->custom_value2)
                {{ $client->account->custom_client_label2 . ': ' . $client->custom_value2 }}<br/>
            @endif

            @if ($client->work_phone)
                <i class="fa fa-phone" style="width: 20px"></i>{{ Utils::formatPhoneNumber($client->work_phone) }}
            @endif

            @if ($client->private_notes)
                <p><i>{{ $client->private_notes }}</i></p>
            @endif
		  	
  	        @if ($client->client_industry)
                {{ $client->client_industry->name }}<br/>
            @endif
            @if ($client->client_size)
                {{ $client->client_size->name }}<br/>
            @endif            

		  	@if ($client->website)
		  	   <p>{!! $client->getWebsite() !!}</p>
            @endif

            @if ($client->language)
                <p><i class="fa fa-language" style="width: 20px"></i>{{ $client->language->name }}</p>
            @endif

		  	<p>{{ $client->payment_terms ? trans('texts.payment_terms') . ": Net " . $client->payment_terms : '' }}</p>
		</div>

		<div class="col-md-3">
			<h3>{{ trans('texts.contacts') }}</h3>
		  	@foreach ($client->contacts as $contact)
                @if ($contact->first_name || $contact->last_name)
                    <b>{{ $contact->first_name.' '.$contact->last_name }}</b><br/>
                @endif
                @if ($contact->email)
                    <i class="fa fa-envelope" style="width: 20px"></i>{!! HTML::mailto($contact->email, $contact->email) !!}<br/>
                @endif
                @if ($contact->phone)
                    <i class="fa fa-phone" style="width: 20px"></i>{!! Utils::formatPhoneNumber($contact->phone) !!}<br/>
                @endif		  		
		  	@endforeach
		</div>

		<div class="col-md-6">
			<h3>{{ trans('texts.standing') }}
			<table class="table" style="width:300px">
				<tr>
					<td><small>{{ trans('texts.paid_to_date') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($client->paid_to_date, $client->getCurrencyId()) }}</td>
				</tr>
				<tr>
					<td><small>{{ trans('texts.balance') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($client->balance, $client->getCurrencyId()) }}</td>
				</tr>
				@if ($credit > 0)
				<tr>
					<td><small>{{ trans('texts.credit') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($credit, $client->getCurrencyId()) }}</td>
				</tr>
				@endif
			</table>
			</h3>

		</div>
	</div>
    </div>
    </div>

	<ul class="nav nav-tabs nav-justified">
		{!! HTML::tab_link('#activity', trans('texts.activity'), true) !!}
        @if ($hasTasks)
            {!! HTML::tab_link('#tasks', trans('texts.tasks')) !!}
        @endif
		@if ($hasQuotes && Utils::isPro())
			{!! HTML::tab_link('#quotes', trans('texts.quotes')) !!}
		@endif
		{!! HTML::tab_link('#invoices', trans('texts.invoices')) !!}
		{!! HTML::tab_link('#payments', trans('texts.payments')) !!}
		{!! HTML::tab_link('#credits', trans('texts.credits')) !!}
	</ul>

	<div class="tab-content">

        <div class="tab-pane active" id="activity">

			{!! Datatable::table()
		    	->addColumn(
		    		trans('texts.date'),
		    		trans('texts.message'),
		    		trans('texts.balance'),
		    		trans('texts.adjustment'))
		    	->setUrl(url('api/activities/'. $client->public_id))
		    	->setOptions('sPaginationType', 'bootstrap')
		    	->setOptions('bFilter', false)
		    	->setOptions('aaSorting', [['0', 'desc']])
		    	->render('datatable') !!}

        </div>

    @if ($hasTasks)
        <div class="tab-pane" id="tasks">

            {!! Datatable::table()
                ->addColumn(
                    trans('texts.date'),
                    trans('texts.duration'),
                    trans('texts.description'),
                    trans('texts.status'))
                ->setUrl(url('api/tasks/'. $client->public_id))
                ->setOptions('sPaginationType', 'bootstrap')
                ->setOptions('bFilter', false)
                ->setOptions('aaSorting', [['0', 'desc']])
                ->render('datatable') !!}

        </div>
    @endif


    @if (Utils::isPro() && $hasQuotes)
        <div class="tab-pane" id="quotes">

			{!! Datatable::table()
		    	->addColumn(
	    			trans('texts.quote_number'),
	    			trans('texts.quote_date'),
	    			trans('texts.total'),
	    			trans('texts.valid_until'),
	    			trans('texts.status'))
		    	->setUrl(url('api/quotes/'. $client->public_id))
		    	->setOptions('sPaginationType', 'bootstrap')
		    	->setOptions('bFilter', false)
		    	->setOptions('aaSorting', [['0', 'desc']])
		    	->render('datatable') !!}

        </div>
    @endif

		<div class="tab-pane" id="invoices">

			@if ($hasRecurringInvoices)
				{!! Datatable::table()
			    	->addColumn(
			    		trans('texts.frequency_id'),
			    		trans('texts.start_date'),
			    		trans('texts.end_date'),
			    		trans('texts.invoice_total'))
			    	->setUrl(url('api/recurring_invoices/' . $client->public_id))
			    	->setOptions('sPaginationType', 'bootstrap')
			    	->setOptions('bFilter', false)
			    	->setOptions('aaSorting', [['0', 'asc']])
			    	->render('datatable') !!}
			@endif

			{!! Datatable::table()
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
		    	->setOptions('aaSorting', [['0', 'desc']])
		    	->render('datatable') !!}

        </div>
        <div class="tab-pane" id="payments">

	    	{!! Datatable::table()
						->addColumn(
			    			trans('texts.invoice'),
			    			trans('texts.transaction_reference'),
			    			trans('texts.method'),
			    			trans('texts.payment_amount'),
			    			trans('texts.payment_date'))
				->setUrl(url('api/payments/' . $client->public_id))
				->setOptions('sPaginationType', 'bootstrap')
				->setOptions('bFilter', false)
				->setOptions('aaSorting', [['0', 'desc']])
				->render('datatable') !!}

        </div>
        <div class="tab-pane" id="credits">

	    	{!! Datatable::table()
						->addColumn(
								trans('texts.credit_amount'),
								trans('texts.credit_balance'),
								trans('texts.credit_date'),
								trans('texts.private_notes'))
				->setUrl(url('api/credits/' . $client->public_id))
				->setOptions('sPaginationType', 'bootstrap')
				->setOptions('bFilter', false)
				->setOptions('aaSorting', [['0', 'asc']])
				->render('datatable') !!}

        </div>
    </div>

	<script type="text/javascript">

	$(function() {
		$('.normalDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('clients/' . $client->public_id . '/edit') }}';
		});
		$('.primaryDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('invoices/create/' . $client->public_id ) }}';
		});
	});

	function onArchiveClick() {
		$('#action').val('archive');
		$('.mainForm').submit();
	}

	function onRestoreClick() {
		$('#action').val('restore');
		$('.mainForm').submit();
	}

	function onDeleteClick() {
		if (confirm("{!! trans('texts.are_you_sure') !!}")) {
			$('#action').val('delete');
			$('.mainForm').submit();
		}
	}

	</script>

@stop
