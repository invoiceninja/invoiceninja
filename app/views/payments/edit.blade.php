@extends('header')


@section('onReady')
	$('input#name').focus();
@stop

@section('content')

	
	{{ Former::open($url)->addClass('col-md-10 col-md-offset-1 main_form')->method($method)->rules(array(
		'client' => 'required',
		'invoice' => 'required',		
  		'amount' => 'required',		
	)); }}

	@if ($payment)
		{{-- Former::populate($payment) --}}
	@else
		{{ Former::populateField('payment_date', date('Y-m-d')) }}
	@endif

	
	<div class="row">
		<div class="col-md-8">

			@if ($payment)
				{{ Former::legend('Edit Payment') }}
			@else
				{{ Former::legend('New Payment') }}
			@endif

			{{ Former::select('client')->addOption('', '')->addGroupClass('client-select') }}
			{{ Former::select('invoice')->addOption('', '')->addGroupClass('invoice-select') }}
			{{ Former::text('amount') }}
			{{ Former::text('payment_date')->data_date_format(DEFAULT_DATE_PICKER_FORMAT) }}
			{{ Former::select('currency_id')->addOption('','')->label('Currency')
				->fromQuery($currencies, 'name', 'id')->select(Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY)) }}

		</div>
		<div class="col-md-6">

		</div>
	</div>

	<center style="margin-top:16px">
		{{ Button::lg_primary_submit('Save') }} &nbsp;|&nbsp;
		{{ link_to('payments/' . ($payment ? $payment->public_id : ''), 'Cancel') }}	
	</center>

	{{ Former::close() }}

	<script type="text/javascript">

	var invoices = {{ $invoices }};
	var clients = {{ $clients }};
	var clientMap = {};
	var invoiceMap = {};
	var invoicesForClientMap = {};

	/*
	function compareClient(a,b) {
	  if (a.name < b.name)
	     return -1;
	  if (a.name> b.name)
	    return 1;
	  return 0;
	}
	*/

	$(function() {

		var $input = $('select#client');		
		
		for (var i=0; i<invoices.length; i++) {
			var invoice = invoices[i];
			var client = invoice.client;			

			if (!invoicesForClientMap.hasOwnProperty(client.public_id)) {
				invoicesForClientMap[client.public_id] = [];				
			}

			invoicesForClientMap[client.public_id].push(invoice);
			invoiceMap[invoice.public_id] = invoice;
			//clientMap[invoice.public_id] = invoice.client;
		}

		for (var i=0; i<clients.length; i++) {
			var client = clients[i];
			clientMap[client.public_id] = client;
		}

		//clients.sort(compareClient);
		$input.append(new Option('', ''));	
		for (var i=0; i<clients.length; i++) {
			var client = clients[i];
			$input.append(new Option(getClientDisplayName(client), client.public_id));
		}	

		@if ($clientPublicId)
			$('select#client').val({{ $clientPublicId }});
		@endif		
		
		$input.combobox();
		$input.on('change', function(e) {						
			console.log('client change');
			var clientId = $('input[name=client]').val();
			var invoiceId = $('input[name=invoice]').val();						
			var invoice = invoiceMap[invoiceId];
			if (invoice && invoice.client.public_id == clientId) {
				console.log('values the same:' + $('select#client').prop('selected'))
				e.preventDefault();
				return;
			}
			setComboboxValue($('.invoice-select'), '', '');				
			$invoiceCombobox = $('select#invoice');
			$invoiceCombobox.find('option').remove().end().combobox('refresh');			
			$invoiceCombobox.append(new Option('', ''));
			var list = clientId ? (invoicesForClientMap.hasOwnProperty(clientId) ? invoicesForClientMap[clientId] : []) : invoices;
			for (var i=0; i<list.length; i++) {
				var invoice = list[i];
				var client = clientMap[invoice.client.public_id];
				$invoiceCombobox.append(new Option(invoice.invoice_number + ' - ' + getClientDisplayName(client),  invoice.public_id));
			}
			$('select#invoice').combobox('refresh');
		}).trigger('change');

		var $input = $('select#invoice').on('change', function(e) {			
			$clientCombobox = $('select#client');
			var invoiceId = $('input[name=invoice]').val();						
			if (invoiceId) {
				var invoice = invoiceMap[invoiceId];				
				var client = clientMap[invoice.client.public_id];
				setComboboxValue($('.client-select'), client.public_id, getClientDisplayName(client));
			}
		});
		$input.combobox();

		$('#currency_id').combobox();

		$('#payment_date').datepicker({
			autoclose: true,
			todayHighlight: true
		});
	});

	</script>

@stop
