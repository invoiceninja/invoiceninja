@extends('header')


@section('onReady')
	$('input#name').focus();
@stop

@section('content')

	
	{{ Former::open($url)->addClass('col-md-10 col-md-offset-1 main_form')->method($method)->rules(array(
		'client' => 'required',
  		'amount' => 'required',		
	)); }}

	@if ($payment)
		{{-- Former::populate($payment) --}}
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
			{{ Former::text('payment_date') }}

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

			if (!invoiceMap.hasOwnProperty(client.public_id)) {
				invoiceMap[client.public_id] = [];				
			}

			invoiceMap[client.public_id].push(invoice);
			clientMap[invoice.public_id] = invoice.client;						
		}

		//clients.sort(compareClient);
		$input.append(new Option('', ''));	
		for (var i=0; i<clients.length; i++) {
			var client = clients[i];
			$input.append(new Option(client.name, client.public_id));
		}	

		$input.on('change', function(e) {						
			console.log('client change');
			var clientId = $('input[name=client]').val();
			var invoiceId = $('input[name=invoice]').val();						
			if (clientMap.hasOwnProperty(invoiceId) && clientMap[invoiceId].public_id == clientId) {
				console.log('values the same:' + $('select#client').prop('selected'))
				e.preventDefault();
				return;
			}
			setComboboxValue($('.invoice-select'), '', '');				
			$invoiceCombobox = $('select#invoice');
			$invoiceCombobox.find('option').remove().end().combobox('refresh');			
			$invoiceCombobox.append(new Option('', ''));
			var list = clientId ? (invoiceMap.hasOwnProperty(clientId) ? invoiceMap[clientId] : []) : invoices;
			for (var i=0; i<list.length; i++) {
				var invoice = list[i];
				$invoiceCombobox.append(new Option(invoice.invoice_number + ' - ' + invoice.invoice_date + ' - ' + invoice.client.name,  invoice.public_id));
			}
			$('select#invoice').combobox('refresh');
		}).trigger('change');
		$input.combobox();

		var $input = $('select#invoice').on('change', function(e) {			
			$clientCombobox = $('select#client');
			var invoiceId = $('input[name=invoice]').val();						
			if (invoiceId) {
				var client = clientMap[invoiceId];
				setComboboxValue($('.client-select'), client.public_id, client.name);
			}
		});
		$input.combobox();

		$('#payment_date').datepicker({
			autoclose: true,
			todayHighlight: true
		});

	});

	</script>

@stop
