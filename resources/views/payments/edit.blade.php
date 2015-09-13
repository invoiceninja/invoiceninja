@extends('header')

@section('content')
	
	{!! Former::open($url)->addClass('col-md-10 col-md-offset-1 warn-on-exit')->method($method)->rules(array(
		'client' => 'required',
		'invoice' => 'required',		
		'amount' => 'required',		
	)) !!}

    @if ($payment)
        {!! Former::populate($payment) !!}
    @endif

	
	<div class="row">
		<div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
            <div class="panel-body">

            @if (!$payment)
			 {!! Former::select('client')->addOption('', '')->addGroupClass('client-select') !!}
			 {!! Former::select('invoice')->addOption('', '')->addGroupClass('invoice-select') !!}
			 {!! Former::text('amount') !!}
            @endif

            @if (!$payment || !$payment->account_gateway_id)
			 {!! Former::select('payment_type_id')
                    ->addOption('','')
                    ->fromQuery($paymentTypes, 'name', 'id')
                    ->addGroupClass('payment-type-select') !!}
            @endif

			{!! Former::text('payment_date')
                        ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))
                        ->addGroupClass('payment_date')
                        ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
			{!! Former::text('transaction_reference') !!}

            @if (!$payment)
                {!! Former::checkbox('email_receipt')->label('&nbsp;')->text(trans('texts.email_receipt')) !!}
            @endif

            </div>
            </div>

		</div>
	</div>


	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->appendIcon(Icon::create('remove-circle'))->asLinkTo(URL::to('/payments'))->large() !!}
        {!! Button::success(trans('texts.save'))->appendIcon(Icon::create('floppy-disk'))->submit()->large() !!}
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

        @if (!$payment && !$clientPublicId)
            $('.client-select input.form-control').focus();
        @elseif (!$payment && !$invoicePublicId)
            $('.invoice-select input.form-control').focus();
        @elseif (!$payment)
            $('#amount').focus();
        @endif

        $('.payment_date .input-group-addon').click(function() {
            toggleDatePicker('payment_date');
        });
	});

	</script>

@stop