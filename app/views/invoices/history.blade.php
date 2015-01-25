@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/pdf_viewer.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/compatibility.js') }}" type="text/javascript"></script>


  <script>

    var invoiceDesigns = {{ $invoiceDesigns }};
    var currentInvoice = {{ $invoice }};
    var versionsJson = {{ $versionsJson }};
    
    function getPDFString() {

        var version = $('#version').val();
        var invoice;

        if (parseInt(version)) {
            invoice = versionsJson[version];
        } else {
            invoice = currentInvoice;
        }

        invoice.image = window.accountLogo;

        var invoiceDesignId = parseInt(invoice.invoice_design_id);
        var invoiceDesign = _.findWhere(invoiceDesigns, {id: invoiceDesignId});
        if (!invoiceDesign) {
            invoiceDesign = invoiceDesigns[0];
        }
        
        var doc = generatePDF(invoice, invoiceDesign.javascript, true);
        if (!doc) {
            return;
        }
        return doc.output('datauristring');
    }

    $(function() {   
      refreshPDF();
    });

  </script> 

@stop

@section('content')

    {{ Former::open()->addClass('form-inline')->onchange('refreshPDF()') }}
    {{ Former::select('version')->options($versionsSelect)->label(trans('select_version')) }}
    {{ Button::success_link(URL::to($invoice->getEntityType() . 's/' . $invoice->public_id . '/edit'), trans('texts.edit_' . $invoice->getEntityType()), array('class' => 'pull-right')) }}    
    {{ Former::close() }}

    <br/>&nbsp;<br/>

    @include('invoices.pdf', ['account' => Auth::user()->account, 'pdfHeight' => 800])

@stop