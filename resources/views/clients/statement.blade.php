@extends('header')

@section('head')
    @parent

    @include('money_script')
    @foreach (Auth::user()->account->getFontFolders() as $font)
        <script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    @endforeach
    <script src="{{ asset('pdf.built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

    <script>

        var invoiceDesigns = {!! \App\Models\InvoiceDesign::getDesigns() !!};
        var invoiceFonts = {!! Cache::get('fonts') !!};
        var currentInvoice = {!! $invoice !!};
        var invoice = {!! $invoice !!};

        function getPDFString(cb) {

            invoice.is_statement = true;
            invoice.image = window.accountLogo;
            invoice.features = {
                  customize_invoice_design:{{ Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ? 'true' : 'false' }},
                  remove_created_by:{{ Auth::user()->hasFeature(FEATURE_REMOVE_CREATED_BY) ? 'true' : 'false' }},
                  invoice_settings:{{ Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS) ? 'true' : 'false' }}
              };

            var invoiceDesignId = parseInt(invoice.invoice_design_id);
            // We don't currently support the hipster design to be used as a statement
            if (invoiceDesignId == 8) {
                invoiceDesignId = 1;
            }
            var invoiceDesign = _.findWhere(invoiceDesigns, {id: invoiceDesignId});
            if (!invoiceDesign) {
                invoiceDesign = invoiceDesigns[0];
            }

            generatePDF(invoice, invoiceDesign.javascript, true, cb);
        }

        $(function() {
          refreshPDF();
        });

        function onDownloadClick() {
            var doc = generatePDF(invoice, invoiceDesigns[0].javascript, true);
            doc.save("{{ str_replace(' ', '_', trim($client->getDisplayName())) . '-' . trans('texts.statement') }}" + '.pdf');
        }

    </script>

@stop

@section('content')

    <div class="pull-right">
        {!! Button::normal(trans('texts.download_pdf'))
                ->withAttributes(['onclick' => 'onDownloadClick()'])
                ->appendIcon(Icon::create('download-alt')) !!}
        {!! Button::primary(trans('texts.view_client'))
                ->asLinkTo($client->present()->url) !!}
    </div>

    <ol class="breadcrumb pull-left">
      <li>{{ link_to('/clients', trans('texts.clients')) }}</li>
      <li class='active'>{{ $client->getDisplayName() }}</li>
    </ol>

    <p>&nbsp;</p>
    <p>&nbsp;</p>

    @include('invoices.pdf', ['account' => Auth::user()->account, 'pdfHeight' => 800])

@stop
