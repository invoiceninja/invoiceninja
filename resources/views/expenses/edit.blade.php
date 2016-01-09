@extends('header')

@section('content')
	
	{!! Former::open($url)->addClass('col-md-10 col-md-offset-1 warn-on-exit')->method($method)->rules(array(
		'public_notes' => 'required',
  		'amount' => 'required',
		'expense_date' => 'required',
	)) !!}

	@if ($expense)
		{!! Former::populate($expense) !!}
        {!! Former::hidden('public_id') !!}
	@endif

	
	<div class="row">
        <div class="col-md-10 col-md-offset-1">

            <div class="panel panel-default">
	            <div class="panel-body">
				{!! Former::select('vendor')->addOption('', '')->addGroupClass('client-select') !!}
				{!! Former::text('expense_date')
                        ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
                        ->addGroupClass('expense_date')->label(trans('texts.expense_date'))
                        ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
				{!! Former::text('amount')->label(trans('texts.expense_amount')) !!}
				{!! Former::text('amount_cur')->label(trans('texts.expense_amount_in_cur')) !!}
				{!! Former::select('currency_id')->addOption('','')
	                ->placeholder($account->currency ? $account->currency->name : '')
	                ->fromQuery($currencies, 'name', 'id') !!}
				{!! Former::text('exchange_rate')->append(trans('texts.expense_exchange_rate_100')) !!}
				{!! Former::textarea('private_notes') !!}
				{!! Former::textarea('public_notes') !!}
				{!! Former::checkbox('should_be_invoiced') !!}
				{!! Former::select('client')->addOption('', '')->addGroupClass('client-select') !!}
	            </div>
            </div>
        </div>
    </div>

	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/dashboard'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

	{!! Former::close() !!}

	<script type="text/javascript">

	
	var vendors = {!! $vendors !!};
	var clients = {!! $clients !!};

	$(function() {

		var $vendorSelect = $('select#vendor');		
		for (var i = 0; i < vendors.length; i++) {
			var vendor = vendors[i];
			$vendorSelect.append(new Option(getVendorDisplayName(vendor), vendor.public_id));
		}	

		if ({{ $vendorPublicId ? 'true' : 'false' }}) {
			$vendorSelect.val({{ $vendorPublicId }});
		}

		$vendorSelect.combobox();
		
		$('#currency_id').combobox();
		$('#expense_date').datepicker('update', new Date());

        @if (!$vendorPublicId)
            $('.vendor-select input.form-control').focus();
        @else
            $('#amount').focus();
        @endif

        $('.expense_date .input-group-addon').click(function() {
            toggleDatePicker('expense_date');
        });


        var $clientSelect = $('select#client');     
        for (var i=0; i<clients.length; i++) {
            var client = clients[i];
            $clientSelect.append(new Option(getClientDisplayName(client), client.public_id));
        }   

        if ({{ $clientPublicId ? 'true' : 'false' }}) {
            $clientSelect.val({{ $clientPublicId }});
        }

        $clientSelect.combobox();
     });

	</script>
@stop