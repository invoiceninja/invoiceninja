@extends('header')

@section('head')
    @parent

    @include('money_script')

    <style type="text/css">
        .input-group-addon {
            min-width: 40px;
        }
    </style>
@stop

@section('content')

	{!! Former::open($url)
        ->addClass('col-md-10 col-md-offset-1 warn-on-exit main-form')
        ->onsubmit('onFormSubmit(event)')
        ->method($method)
        ->rules(array(
    		'client' => 'required',
    		'invoice' => 'required',
    		'amount' => 'required',
    	)) !!}

    @if ($payment)
        {!! Former::populate($payment) !!}
    @else
        @if ($account->payment_type_id)
            {!! Former::populateField('payment_type_id', $account->payment_type_id) !!}
        @endif
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
        {!! Former::text('action') !!}
    </span>

	<div class="row">
		<div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
            <div class="panel-body">

            @if ($payment)
             {!! Former::plaintext()->label('client')->value($payment->client->present()->link) !!}
             {!! Former::plaintext()->label('invoice')->value($payment->invoice->present()->link) !!}
             {!! Former::plaintext()->label('amount')->value($payment->present()->amount) !!}
            @else
			 {!! Former::select('client')->addOption('', '')->addGroupClass('client-select') !!}
			 {!! Former::select('invoice')->addOption('', '')->addGroupClass('invoice-select') !!}
			 {!! Former::text('amount') !!}

             @if (isset($paymentTypeId) && $paymentTypeId)
               {!! Former::populateField('payment_type_id', $paymentTypeId) !!}
             @endif
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
            {!! Former::textarea('private_notes') !!}

            @if (!$payment)
                {!! Former::checkbox('email_receipt')
                        ->onchange('onEmailReceiptChange()')
                        ->label('&nbsp;')
                        ->text(trans('texts.email_receipt'))
                        ->value(1) !!}
            @endif

            </div>
            </div>

		</div>
	</div>


	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->appendIcon(Icon::create('remove-circle'))->asLinkTo(HTMLUtils::previousUrl('/payments'))->large() !!}
        @if (!$payment || !$payment->is_deleted)
            {!! Button::success(trans('texts.save'))->withAttributes(['id' => 'saveButton'])->appendIcon(Icon::create('floppy-disk'))->submit()->large() !!}
        @endif

        @if ($payment)
            {!! DropdownButton::normal(trans('texts.more_actions'))
                  ->withContents($actions)
                  ->large()
                  ->dropup() !!}
        @endif

	</center>

    @include('partials/refund_payment')

	{!! Former::close() !!}

	<script type="text/javascript">

	var invoices = {!! $invoices !!};
	var clients = {!! $clients !!};

	$(function() {

        @if ($payment)
          $('#payment_date').datepicker('update', '{{ $payment->payment_date }}')
          @if ($payment->payment_type_id != PAYMENT_TYPE_CREDIT)
            $("#payment_type_id option[value='{{ PAYMENT_TYPE_CREDIT }}']").remove();
          @endif
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

        if (isStorageSupported()) {
            if (localStorage.getItem('last:send_email_receipt')) {
                $('#email_receipt').prop('checked', true);
            }
        }
	});

    function onFormSubmit(event) {
        $('#saveButton').attr('disabled', true);
    }

    function submitAction(action) {
        $('#action').val(action);
        $('.main-form').submit();
    }

    function submitForm_payment(action) {
        submitAction(action);
    }

    function onDeleteClick() {
        sweetConfirm(function() {
            submitAction('delete');
        });
    }

    function onEmailReceiptChange() {
        if (! isStorageSupported()) {
            return;
        }
        var checked = $('#email_receipt').is(':checked');
        localStorage.setItem('last:send_email_receipt', checked ? true : '');
    }

	</script>

@stop
