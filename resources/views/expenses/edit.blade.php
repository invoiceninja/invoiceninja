@extends('header')

@section('head')
    @parent

        <style type="text/css">
            .input-group-addon div.checkbox {
                display: inline;
            }
            div.client-select > div > div > span.input-group-addon {
                padding-right: 30px;
            }
        </style>
@stop

@section('content')
	
	{!! Former::open($url)->addClass('warn-on-exit')->method($method) !!}

	@if ($expense)
		{!! Former::populate($expense) !!}
        {!! Former::populateField('should_be_invoiced', intval($expense->should_be_invoiced)) !!}
        {!! Former::hidden('public_id') !!}
	@endif

    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <div class="col-md-6">
    				{!! Former::select('vendor')->addOption('', '')
                            ->addGroupClass('vendor-select') !!}
    				{!! Former::select('client')
                            ->addOption('', '')
                            ->addGroupClass('client-select')
                            ->append(Former::checkbox('should_be_invoiced')->raw() . 
                                trans('texts.invoice')) !!}
                    {!! Former::text('expense_date')
                            ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
                            ->addGroupClass('expense_date')->label(trans('texts.expense_date'))
                            ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
                    {!! Former::select('currency_id')->addOption('','')
                        ->placeholder($account->currency ? $account->currency->name : '')
                        ->fromQuery($currencies, 'name', 'id') !!}

                    {!! Former::text('amount')->label(trans('texts.expense_amount')) !!}
                    {!! Former::text('foreign_amount') !!}
                    {!! Former::text('exchange_rate') !!}
	            </div>
                <div class="col-md-6">

                    {!! Former::textarea('public_notes')->rows(8) !!}
                    {!! Former::textarea('private_notes')->rows(8) !!}
                </div>
            </div>
        </div>
    </div>

	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/expenses'))->appendIcon(Icon::create('remove-circle')) !!}
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