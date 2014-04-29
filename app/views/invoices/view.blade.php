@extends('header')

@section('head')
	@parent

		<script src="{{ asset('js/pdf_viewer.js') }}" type="text/javascript"></script>
		<script src="{{ asset('js/compatibility.js') }}" type="text/javascript"></script>
@stop

@section('content')

	@if ($invoice->client->account->isGatewayConfigured() && !$invoice->isPaid())
		<div class="pull-right" style="width:270px">
			{{ Button::normal(trans('texts.download_pdf'), array('onclick' => 'onDownloadClick()', 'class' => 'btn-lg')) }}
			{{ Button::primary_link(URL::to('payment/' . $invitation->invitation_key), trans('texts.pay_now'), array('class' => 'btn-lg pull-right')) }}
		</div>		
	@else 
		<div class="pull-right">
			{{ Button::primary('Download PDF', array('onclick' => 'onDownloadClick()', 'class' => 'btn-lg')) }}
		</div>		
	@endif
	
	<div class="clearfix"></div><p>&nbsp;</p>

	<iframe id="theFrame" frameborder="1" width="100%" height="1180" style="display:none;margin: 0 auto"></iframe>
	<canvas id="theCanvas" style="display:none;width:100%;border:solid 1px #CCCCCC;"></canvas>

	<script type="text/javascript">

		$(function() {
			window.invoice = {{ $invoice->toJson() }};
			window.logoImages = {};

	    logoImages.imageLogo1 = "{{ HTML::image_data('images/report_logo1.jpg') }}";
	    logoImages.imageLogoWidth1 =120;
	    logoImages.imageLogoHeight1 = 40

	    logoImages.imageLogo2 = "{{ HTML::image_data('images/report_logo2.jpg') }}";
	    logoImages.imageLogoWidth2 =325/2;
	    logoImages.imageLogoHeight2 = 81/2;

	    logoImages.imageLogo3 = "{{ HTML::image_data('images/report_logo3.jpg') }}";
	    logoImages.imageLogoWidth3 =325/2;
	    logoImages.imageLogoHeight3 = 81/2;

			@if (file_exists($invoice->client->account->getLogoPath()))
				invoice.image = "{{ HTML::image_data($invoice->client->account->getLogoPath()) }}";
				invoice.imageWidth = {{ $invoice->client->account->getLogoWidth() }};
				invoice.imageHeight = {{ $invoice->client->account->getLogoHeight() }};
			@endif

		  var NINJA = NINJA || {};
	    NINJA.primaryColor = "{{ $invoice->client->account->primary_color }}";
  	  NINJA.secondaryColor = "{{ $invoice->client->account->secondary_color }}";

			var doc = generatePDF(invoice);
			if (!doc) return;
			var string = doc.output('datauristring');
						
			if (isFirefox || (isChrome && !isChromium)) {
				$('#theFrame').attr('src', string).show();
			} else {
				var pdfAsArray = convertDataURIToBinary(string);	
			    PDFJS.getDocument(pdfAsArray).then(function getPdfHelloWorld(pdf) {

			      pdf.getPage(1).then(function getPageHelloWorld(page) {
			        var scale = 1.5;
			        var viewport = page.getViewport(scale);

			        var canvas = document.getElementById('theCanvas');
			        var context = canvas.getContext('2d');
			        canvas.height = viewport.height;
			        canvas.width = viewport.width;

			        page.render({canvasContext: context, viewport: viewport});
			        $('#theCanvas').show();
			      });
			    });				
			 }
		});
		
		var invoiceLabels = {{ json_encode($invoiceLabels) }};

		function onDownloadClick() {
			var doc = generatePDF(invoice, true);
			doc.save('Invoice-' + invoice.invoice_number + '.pdf');
		}


	</script>

@stop