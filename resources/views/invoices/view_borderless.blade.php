@extends('master')

@section('head')
	@parent

		@include('money_script')

		@foreach ($invoice->client->account->getFontFolders() as $font)
        	<script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    	@endforeach

        <script src="{{ asset('pdf.built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

@stop

@section('body')
	<script type="text/javascript">

		window.invoice = {!! $invoice !!};
		invoice.features = {
            customize_invoice_design:{{ $invoice->client->account->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ? 'true' : 'false' }},
            remove_created_by:{{ $invoice->client->account->hasFeature(FEATURE_REMOVE_CREATED_BY) ? 'true' : 'false' }},
            invoice_settings:{{ $invoice->client->account->hasFeature(FEATURE_INVOICE_SETTINGS) ? 'true' : 'false' }}
        };
		invoice.is_quote = {{ $invoice->isQuote() ? 'true' : 'false' }};
		invoice.contact = {!! $contact !!};

		function getPDFString(cb) {
	  	    return generatePDF(invoice, invoice.invoice_design.javascript, true, cb);
		}

        if (window.hasOwnProperty('pjsc_meta')) {
            window['pjsc_meta'].remainingTasks++;
        }

		function waitForSignature() {
			if (window.signatureAsPNG || ! invoice.invitations[0].signature_base64) {
				writePdfAsString();
			} else {
				window.setTimeout(waitForSignature, 100);
			}
		}

		function writePdfAsString() {
			doc = getPDFString();
			doc.getDataUrl(function(pdfString) {
				document.write(pdfString);
				document.close();
				if (window.hasOwnProperty('pjsc_meta')) {
					window['pjsc_meta'].remainingTasks--;
				}
			});
		}

		$(function() {
            @if (Input::has('phantomjs'))
				@if (Input::has('phantomjs_balances'))
					document.write(calculateAmounts(invoice).total_amount);
					document.close();
					if (window.hasOwnProperty('pjsc_meta')) {
						window['pjsc_meta'].remainingTasks--;
					}
				@else
					@if ($account->signature_on_pdf)
						refreshPDF();
						waitForSignature();
					@else
						writePdfAsString();
					@endif
				@endif
            @else
				@if (request()->download)
					try {
						var doc = generatePDF(invoice, invoice.invoice_design.javascript, true);
						var fileName = invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice;
						doc.save(fileName + '_' + invoice.invoice_number + '.pdf');
					} catch (exception) {
						if (location.href.indexOf('/view/') > 0) {
							location.href = location.href.replace('/view/', '/download/');
						}
					}
				@else
					refreshPDF();
				@endif
            @endif
		});

	</script>

	@include('invoices.pdf', ['account' => $invoice->client->account])
@stop
