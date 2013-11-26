@extends('header')

@section('content')

	@if (true || $invoice->client->account->isGatewayConfigured())
		{{ Button::primary_link(URL::to('payment/' . $invoice->invoice_key), 'Pay Now', array('class' => 'btn-lg pull-right')) }}
		<div class="clearfix"></div><p>&nbsp;</p>
	@endif

	<iframe frameborder="1" width="100%" height="600" style="display:block;margin: 0 auto"></iframe>	
	
	<script type="text/javascript">

		$(function() {
			var invoice = {{ $invoice->toJson() }};
			@if (file_exists($invoice->client->account->getLogoPath()))
				invoice.image = "{{ HTML::image_data($invoice->client->account->getLogoPath()) }}";
				invoice.imageWidth = {{ $invoice->client->account->getLogoWidth() }};
				invoice.imageHeight = {{ $invoice->client->account->getLogoHeight() }};
			@endif
			var doc = generatePDF(invoice);
			var string = doc.output('datauristring');
			$('iframe').attr('src', string);
		});

	</script>

@stop