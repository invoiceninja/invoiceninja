@extends(! empty($extends) ? $extends : 'header')

@section('head')
    @parent

    <script src="{{ asset('js/daterangepicker.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/daterangepicker.css') }}" rel="stylesheet" type="text/css"/>

    @include('money_script')
    @foreach ($account->getFontFolders() as $font)
        <script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    @endforeach
    <script src="{{ asset('pdf.built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

    <script>

        var invoiceDesign = JSON.stringify({!!
            //Utils::getFromCache($account->invoice_design_id ?: 1, 'invoiceDesigns')->pdfmake
            Utils::getFromCache(1, 'invoiceDesigns')->pdfmake
        !!});
        var invoiceFonts = {!! Cache::get('fonts') !!};

        var statementStartDate = moment("{{ $startDate }}");
		var statementEndDate = moment("{{ $endDate }}");
        var dateRanges = {!! $account->present()->dateRangeOptions !!};

        function getPDFString(cb) {
            invoice.is_statement = true;
            invoice.image = window.accountLogo;
            invoice.features = {
                  customize_invoice_design:{{ $account->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ? 'true' : 'false' }},
                  remove_created_by:{{ $account->hasFeature(FEATURE_REMOVE_CREATED_BY) ? 'true' : 'false' }},
                  invoice_settings:{{ $account->hasFeature(FEATURE_INVOICE_SETTINGS) ? 'true' : 'false' }}
              };

            generatePDF(invoice, invoiceDesign, true, cb);
        }

        $(function() {
            if (isStorageSupported()) {
				var lastRange = localStorage.getItem('last:statement_range');
                var lastStatusId = localStorage.getItem('last:statement_status_id');
                var lastShowPayments = localStorage.getItem('last:statement_show_payments');
                var lastShowAging = localStorage.getItem('last:statement_show_aging');
				lastRange = dateRanges[lastRange];
				if (lastRange) {
					statementStartDate = lastRange[0];
					statementEndDate = lastRange[1];
				}
                if (lastStatusId) {
                    $('#status_id').val(lastStatusId);
                }
                if (lastShowPayments) {
                    $('#show_payments').prop('checked', true);
                }
                if (lastShowAging) {
                    $('#show_aging').prop('checked', true);
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

            var url = '{{ request()->url() }}' +
                '?status_id=' + statusId +
                '&start_date=' + statementStartDate.format('YYYY-MM-DD') +
                '&end_date=' + statementEndDate.format('YYYY-MM-DD') +
                '&show_payments=' + ($('#show_payments').is(':checked') ? '1' : '') +
                '&show_aging=' + ($('#show_aging').is(':checked') ? '1' : '') +
                '&json=true';

            $.get(url, function(response) {
                invoice = currentInvoice = JSON.parse(response);
                refreshPDF();
            });

            if (isStorageSupported()) {
                localStorage.setItem('last:statement_status_id', $('#status_id').val());
                localStorage.setItem('last:statement_show_payments', $('#show_payments').is(':checked') ? '1' : '');
                localStorage.setItem('last:statement_show_aging', $('#show_aging').is(':checked') ? '1' : '');
            }
        }

        function onDownloadClick() {
            var doc = generatePDF(invoice, invoiceDesign, true);
            doc.save("{{ str_replace(' ', '_', trim($client->getDisplayName())) . '-' . trans('texts.statement') }}" + '.pdf');
        }

    </script>

@stop

@section('content')

    @if (empty($extends))
        <div class="pull-right">
            {!! Button::normal(trans('texts.download'))
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
    @endif

    <div class="well" style="background: #eeeeee; padding-bottom:30px;">
        <div class="pull-left">
            {!! Former::inline_open()->onchange('refreshData()') !!}

            {{ trans('texts.status') }}

            &nbsp;&nbsp;

            {!! Former::select('status_id')
                    ->label('status')
                    ->addOption(trans('texts.all'), 'false')
                    ->addOption(trans('texts.unpaid'), INVOICE_STATUS_UNPAID)
                    ->addOption(trans('texts.paid'), INVOICE_STATUS_PAID) !!}

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

            &nbsp;&nbsp;&nbsp;&nbsp;

            @if (empty($extends))
                {!! Former::checkbox('show_payments')->text('show_payments') !!}
                &nbsp;&nbsp;&nbsp;&nbsp;
                {!! Former::checkbox('show_aging')->text('show_aging') !!}
            @else
                {!! Former::checkbox('show_payments')->text('show_payments')->inline() !!}
                &nbsp;&nbsp;&nbsp;&nbsp;
                {!! Former::checkbox('show_aging')->text('show_aging')->inline() !!}
            @endif

            {!! Former::close() !!}

        </div>

        @if (! empty($extends))
            <div class="pull-right">
                {!! Button::normal(trans('texts.download') . ' &nbsp; ')
                        ->withAttributes(['onclick' => 'onDownloadClick()'])
                        ->appendIcon(Icon::create('download-alt')) !!}
            </div>
        @endif
        &nbsp;
    </div>

    @include('invoices.pdf', ['account' => $account])

@stop
