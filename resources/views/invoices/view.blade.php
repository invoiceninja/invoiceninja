@extends('public.header')

@section('head')
	@parent

		@include('money_script')

		@foreach ($invoice->client->account->getFontFolders() as $font)
        <script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    	@endforeach
        <script src="{{ asset('pdf.built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

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
            <div class="pull-right" style="text-align:right">
            @if (Session::get('trackEventAction') === '/buy_pro_plan')
                {!! Button::normal(trans('texts.download_pdf'))->withAttributes(['onclick' => 'onDownloadClick()'])->large() !!}&nbsp;&nbsp;
                {!! Button::primary(trans('texts.return_to_app'))->asLinkTo(URL::to('/dashboard'))->large() !!}
            @elseif ($invoice->is_quote)
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
    			{!! Button::normal(trans('texts.download_pdf'))->withAttributes(['onclick' => 'onDownloadClick()'])->large() !!}
    		@endif
    		</div>
    		<div class="pull-left">
                @if(!empty($documentsZipURL))
                    {!! Button::normal(trans('texts.download_documents', array('size'=>Form::human_filesize($documentsZipSize))))->asLinkTo($documentsZipURL)->large() !!}
                @endif
            </div>
        @endif

		<div class="clearfix"></div><p>&nbsp;</p>
        @if ($account->isPro() && $invoice->hasDocuments())
            <div class="invoice-documents">
            <h3>{{ trans('texts.documents_header') }}</h3>
            <ul>
            @foreach ($invoice->documents as $document)
                <li><a target="_blank" href="{{ $document->getClientUrl($invitation) }}">{{$document->name}} ({{Form::human_filesize($document->size)}})</a></li>
            @endforeach
            @foreach ($invoice->expenses as $expense)
                @foreach ($expense->documents as $document)
                    <li><a target="_blank" href="{{ $document->getClientUrl($invitation) }}">{{$document->name}} ({{Form::human_filesize($document->size)}})</a></li>
                @endforeach
            @endforeach
            </ul>
            </div>
        @endif

        @if ($account->hasFeature(FEATURE_DOCUMENTS) && $account->invoice_embed_documents)
            @foreach ($invoice->documents as $document)
                @if($document->isPDFEmbeddable())
                    <script src="{{ $document->getClientVFSJSUrl() }}" type="text/javascript" async></script>
                @endif
            @endforeach
            @foreach ($invoice->expenses as $expense)
                @foreach ($expense->documents as $document)
                    @if($document->isPDFEmbeddable())
                        <script src="{{ $document->getClientVFSJSUrl() }}" type="text/javascript" async></script>
                    @endif
                @endforeach
            @endforeach
        @endif
		<script type="text/javascript">

			window.invoice = {!! $invoice->toJson() !!};
			invoice.features = {
                customize_invoice_design:{{ $invoice->client->account->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ? 'true' : 'false' }},
                remove_created_by:{{ $invoice->client->account->hasFeature(FEATURE_REMOVE_CREATED_BY) ? 'true' : 'false' }},
                invoice_settings:{{ $invoice->client->account->hasFeature(FEATURE_INVOICE_SETTINGS) ? 'true' : 'false' }}
            };
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

		@include('invoices.pdf', ['account' => $invoice->client->account, 'viewPDF' => true])

		<p>&nbsp;</p>
		<p>&nbsp;</p>

	</div>
@stop
