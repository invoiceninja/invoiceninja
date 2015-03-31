@extends('header')


@section('onReady')
	$('input#name').focus();
@stop

@section('content')

	
	{!! Former::open($url)->addClass('col-md-10 col-md-offset-1 warn-on-exit')->method($method)->rules(array(
		'client' => 'required',
		'invoice' => 'required',		
		'amount' => 'required',		
	)) !!}

    @if ($payment)
        {{ Former::populate($payment) }}
    @endif
	
	<div class="row">
		<div class="col-md-8">

            @if (!$payment)                        
			 {!! Former::select('client')->addOption('', '')->addGroupClass('client-select') !!}
			 {!! Former::select('invoice')->addOption('', '')->addGroupClass('invoice-select') !!}
			 {!! Former::text('amount') !!}
            @endif

			{!! Former::select('payment_type_id')->addOption('','')
				->fromQuery($paymentTypes, 'name', 'id') !!}			
			{!! Former::text('payment_date')->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
			{!! Former::text('transaction_reference') !!}

		</div>
		<div class="col-md-6">

		</div>
	</div>

	<center class="buttons">
        {!! Button::success(trans('texts.save'))->appendIcon(Icon::create('floppy-disk'))->submit()->large() !!}
        {!! Button::withValue(trans('texts.cancel'))->appendIcon(Icon::create('remove-circle'))->asLinkTo('/payments')->large() !!}
	</center>

	{!! Former::close() !!}

	<script type="text/javascript">

	var invoices = {!! $invoices !!};
	var clients = {!! $clients !!};

	$(function() {

        @if ($payment)
          $('#payment_date').datepicker('update', '{{ $payment->payment_date }}')
        @else
          $('#payment_date').datepicker('update', new Date());
		  populateInvoiceComboboxes({{ $clientPublicId }}, {{ $invoicePublicId }});
        @endif

		$('#payment_type_id').combobox();		

	});

	</script>

@stop
