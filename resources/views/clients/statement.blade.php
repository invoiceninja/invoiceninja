@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/daterangepicker.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/daterangepicker.css') }}" rel="stylesheet" type="text/css"/>

    @include('money_script')
    @foreach (Auth::user()->account->getFontFolders() as $font)
        <script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    @endforeach
    <script src="{{ asset('pdf.built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

    <script>

        var invoiceDesigns = {!! \App\Models\InvoiceDesign::getDesigns() !!};
        var invoiceFonts = {!! Cache::get('fonts') !!};

        var statementStartDate = moment("{{ $startDate }}");
		var statementEndDate = moment("{{ $endDate }}");
        var dateRanges = {!! $account->present()->dateRangeOptions !!};

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
            if (isStorageSupported()) {
				var lastRange = localStorage.getItem('last:statement_range');
                var lastStatusId = localStorage.getItem('last:statement_status_id');
				lastRange = dateRanges[lastRange];
				if (lastRange) {
					statementStartDate = lastRange[0];
					statementEndDate = lastRange[1];
				}
                if (lastStatusId) {
                    $('#status_id').val(lastStatusId);
                }
			}

            // Initialize date range selector
            function cb(start, end, label) {
                statementStartDate = start;
                statementEndDate = end;
                $('#reportrange span').html(start.format('{{ $account->getMomentDateFormat() }}') + ' - ' + end.format('{{ $account->getMomentDateFormat() }}'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));

				if (isStorageSupported() && label && label != "{{ trans('texts.custom_range') }}") {
					localStorage.setItem('last:statement_range', label);
				}

                refreshData();
            }

            $('#reportrange').daterangepicker({
                locale: {
                    format: "{{ $account->getMomentDateFormat() }}",
                    customRangeLabel: "{{ trans('texts.custom_range') }}",
                    applyLabel: "{{ trans('texts.apply') }}",
                    cancelLabel: "{{ trans('texts.cancel') }}",
                },
                startDate: statementStartDate,
                endDate: statementEndDate,
                linkedCalendars: false,
				ranges: dateRanges,
            }, cb);

            cb(statementStartDate, statementEndDate);
        });

        function refreshData() {
            var statusId = $('#status_id').val();
            if (statusId == {{ INVOICE_STATUS_UNPAID }}) {
                $('#reportrange').css('color', '#AAA');
                $('#reportrange').css('pointer-events', 'none');
            } else {
                $('#reportrange').css('color', '#000');
                $('#reportrange').css('pointer-events', 'auto');
            }
            var url = "{!! url('/clients/statement/' . $client->public_id) !!}/" + statusId + '/' +
                statementStartDate.format('YYYY-MM-DD') + '/' + statementEndDate.format('YYYY-MM-DD') + '?json=true';
            $.get(url, function(response) {
                invoice = currentInvoice = JSON.parse(response);
                refreshPDF();
            });
        }

        function onStatusChange() {
            if (isStorageSupported()) {
                localStorage.setItem('last:statement_status_id', $('#status_id').val());
            }

            refreshData();
        }

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

    <div class="well" style="background: #eeeeee">
        {!! Former::inline_open() !!}

        {{ trans('texts.status') }}

        &nbsp;&nbsp;

        {!! Former::select('status_id')
                ->onchange('onStatusChange()')
                ->label('status')
                ->addOption(trans('texts.unpaid'), INVOICE_STATUS_UNPAID)
                ->addOption(trans('texts.paid'), INVOICE_STATUS_PAID)
                ->addOption(trans('texts.all'), 'false') !!}

        &nbsp;&nbsp;&nbsp;&nbsp;

        {{ trans('texts.date_range') }}

        &nbsp;&nbsp;

        <span id="reportrange" style="background: #f9f9f9; cursor: pointer; padding: 9px 14px; border: 1px solid #dfe0e1; margin-top: 0px;">
            <i class="glyphicon glyphicon-calendar fa fa-calendar"></i>&nbsp;
            <span></span> <b class="caret"></b>
        </span>

        <div style="display:none">
            {!! Former::text('start_date') !!}
            {!! Former::text('end_date') !!}
        </div>

        {!! Former::close() !!}
    </div>

    @include('invoices.pdf', ['account' => Auth::user()->account, 'pdfHeight' => 800])

@stop
