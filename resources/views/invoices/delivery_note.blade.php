@extends('header')

@section('head')
    @parent

    @include('money_script')
    @foreach (Auth::user()->account->getFontFolders() as $font)
        <script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    @endforeach
    <script src="{{ asset('pdf.built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

  <script>

    var invoice = {!! $invoice !!};
    var invoiceDesign = false;
    var invoiceDesigns = {!! $invoiceDesigns !!};
    var invoiceFonts = {!! $invoiceFonts !!};

    function getPDFString(cb) {
        invoice.image = window.accountLogo;
        invoice.is_delivery_note = true;
        var invoiceDesignId = parseInt(invoice.invoice_design_id);
        invoiceDesign = _.findWhere(invoiceDesigns, {id: invoiceDesignId});
        if (!invoiceDesign) {
            invoiceDesign = invoiceDesigns[0];
        }
        generatePDF(invoice, invoiceDesign.javascript, true, cb);
    }

    function onDownloadClick() {
		trackEvent('/activity', '/download_pdf');
		var doc = generatePDF(invoice, invoiceDesign.javascript, true);
        doc.save('{{ str_replace(' ', '_', trans('texts.delivery_note')) }}-{{ $invoice->invoice_number }}.pdf');
	}

    $(function() {
        refreshPDF();
    });

  </script>

@stop

@section('top-right')
    <div class="pull-right">
        {!! Button::normal(trans('texts.download'))
                ->withAttributes(['onclick' => 'onDownloadClick()', 'id' => 'downloadPdfButton'])
                ->appendIcon(Icon::create('download-alt')) !!}

        {!! Button::primary(trans('texts.edit_' . $invoice->getEntityType()))
                ->asLinkTo(url('/' . $invoice->getEntityType() . 's/' . $invoice->public_id . '/edit'))
                ->appendIcon(Icon::create('edit')) !!}
    </div>
@stop

@section('content')


    @include('invoices.pdf', ['account' => Auth::user()->account, 'pdfHeight' => 800])

    @if (Utils::hasFeature(FEATURE_DOCUMENTS) && $invoice->account->invoice_embed_documents)
        @foreach ($invoice->documents as $document)
            @if($document->isPDFEmbeddable())
                <script src="{{ $document->getVFSJSUrl() }}" type="text/javascript" async></script>
            @endif
        @endforeach
        @foreach ($invoice->expenses as $expense)
            @foreach ($expense->documents as $document)
                @if($document->isPDFEmbeddable())
                    <script src="{{ $document->getVFSJSUrl() }}" type="text/javascript" async></script>
                @endif
            @endforeach
        @endforeach
    @endif
@stop
