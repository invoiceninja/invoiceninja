@extends('public.header')

@section('head')
	@parent

		@include('money_script')
		
		@foreach ($invoice->client->account->getFontFolders() as $font)
        <script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    	@endforeach
        <script src="{{ asset('js/pdf.built.js') }}" type="text/javascript"></script>
        
		<style type="text/css">
			body {
				background-color: #f8f8f8;		
			}
		</style>
@stop

@section('content')

	<div class="container">

        @if ($checkoutComToken)
            @include('partials.checkout_com_payment')
        @else
    		<p>&nbsp;</p>
            <div class="pull-right" style="text-align:right">
            @if ($invoice->is_quote)            
                {!! Button::normal(trans('texts.download_pdf'))->withAttributes(['onclick' => 'onDownloadClick()'])->large() !!}&nbsp;&nbsp;
                @if ($showApprove)
                    {!! Button::success(trans('texts.approve'))->asLinkTo(URL::to('/approve/' . $invitation->invitation_key))->large() !!}
                @endif
    		@elseif ($invoice->client->account->isGatewayConfigured() && !$invoice->isPaid() && !$invoice->is_recurring)
                {!! Button::normal(trans('texts.download_pdf'))->withAttributes(['onclick' => 'onDownloadClick()'])->large() !!}&nbsp;&nbsp;
                @if (count($paymentTypes) > 1)
                    {!! DropdownButton::success(trans('texts.pay_now'))->withContents($paymentTypes)->large() !!}
                @else
                    <a href='{!! $paymentURL !!}' class="btn btn-success btn-lg">{{ trans('texts.pay_now') }}</a>
                @endif            
    		@else 
    			{!! Button::normal('Download PDF')->withAttributes(['onclick' => 'onDownloadClick()'])->large() !!}
    		@endif
    		</div>
        @endif

		<div class="clearfix"></div><p>&nbsp;</p>

		<script type="text/javascript">

			window.invoice = {!! $invoice->toJson() !!};
			invoice.is_pro = {{ $invoice->client->account->isPro() ? 'true' : 'false' }};
			invoice.is_quote = {{ $invoice->is_quote ? 'true' : 'false' }};
			invoice.contact = {!! $contact->toJson() !!};

			function getPDFString(cb) {
    	  	    return generatePDF(invoice, invoice.invoice_design.javascript, true, cb);
			}

            if (window.hasOwnProperty('pjsc_meta')) {
                window['pjsc_meta'].remainingTasks++;
            }

			$(function() {
                @if (Input::has('phantomjs'))
                    doc = getPDFString();
                    doc.getDataUrl(function(pdfString) {
                        document.write(pdfString);
                        document.close();
                        
                        if (window.hasOwnProperty('pjsc_meta')) {
                            window['pjsc_meta'].remainingTasks--;
                        }
                    });
                @else 
                    refreshPDF();
                @endif
			});
			
			function onDownloadClick() {
				var doc = generatePDF(invoice, invoice.invoice_design.javascript, true);
                var fileName = invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice;
				doc.save(fileName + '-' + invoice.invoice_number + '.pdf');
			}

		</script>

		@include('invoices.pdf', ['account' => $invoice->client->account])

		<p>&nbsp;</p>
		<p>&nbsp;</p>

	</div>	

@stop
