@extends('public.header')

@section('head')
	@parent

		@include('script')		
		
		<link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/>

		<script src="{{ asset('js/pdf_viewer.js') }}" type="text/javascript"></script>
		<script src="{{ asset('js/compatibility.js') }}" type="text/javascript"></script>

		<style type="text/css">
			body {
				background-color: #f8f8f8;		
			}
		</style>
@stop

@section('content')

	<div class="container">

		<p>&nbsp;</p>

		@if ($invoice->client->account->isGatewayConfigured() && !$invoice->isPaid() && !$invoice->is_recurring)
			<div class="pull-right" style="width:270px">			
				{{ Button::normal(trans('texts.download_pdf'), array('onclick' => 'onDownloadClick()', 'class' => 'btn-lg')) }}
				{{ Button::success_link(URL::to('payment/' . $invitation->invitation_key), trans('texts.pay_now'), array('class' => 'btn-lg pull-right')) }}
			</div>		
		@else 
			<div class="pull-right">
				{{ Button::primary('Download PDF', array('onclick' => 'onDownloadClick()', 'class' => 'btn-lg')) }}
			</div>		
		@endif
		
		<div class="clearfix"></div><p>&nbsp;</p>

		<script type="text/javascript">

			window.invoice = {{ $invoice->toJson() }};
			invoice.is_pro = {{ $invoice->client->account->isPro() ? 'true' : 'false' }};
			invoice.is_quote = {{ $invoice->is_quote ? 'true' : 'false' }};

			function getPDFString() {
	  	  var doc = generatePDF(invoice, invoice.invoice_design.javascript);
				if (!doc) return;
				return doc.output('datauristring');
			}

			$(function() {
				refreshPDF();
			});
			
			function onDownloadClick() {			
				var doc = generatePDF(invoice, invoice.invoice_design.javascript, true);
				doc.save('Invoice-' + invoice.invoice_number + '.pdf');
			}


		</script>

		@include('invoices.pdf', ['account' => $invoice->client->account])

		<p>&nbsp;</p>
		<p>&nbsp;</p>

	</div>	

@stop