@extends('header')

@section('head')
@parent

    @include('money_script')

    <link href='https://fonts.googleapis.com/css?family=Roboto+Mono' rel='stylesheet' type='text/css'>

    <style>
    .checkbox-inline input[type="checkbox"] {
        margin-left:-20px !important;
    }
    .iframe_url {
        display: none;
    }
    </style>

@stop

@section('content')
@parent

{!! Former::open_for_files()
        ->rules([
            'iframe_url' => 'url',
        ])
        ->addClass('warn-on-exit') !!}

{!! Former::populate($account) !!}
{!! Former::populateField('enable_client_portal', intval($account->enable_client_portal)) !!}
{!! Former::populateField('enable_client_portal_dashboard', intval($account->enable_client_portal_dashboard)) !!}
{!! Former::populateField('enable_portal_password', intval($enable_portal_password)) !!}
{!! Former::populateField('send_portal_password', intval($send_portal_password)) !!}
{!! Former::populateField('enable_buy_now_buttons', intval($account->enable_buy_now_buttons)) !!}
{!! Former::populateField('show_accept_invoice_terms', intval($account->show_accept_invoice_terms)) !!}
{!! Former::populateField('show_accept_quote_terms', intval($account->show_accept_quote_terms)) !!}
{!! Former::populateField('require_invoice_signature', intval($account->require_invoice_signature)) !!}
{!! Former::populateField('require_quote_signature', intval($account->require_quote_signature)) !!}

@include('accounts.nav', ['selected' => ACCOUNT_CLIENT_PORTAL])

<div class="row">
    <div class="col-md-12">

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.settings') !!}</h3>
            </div>
            <div class="panel-body">

                <div role="tabpanel">
                    <ul class="nav nav-tabs" role="tablist" style="border: none">
                        <li role="presentation" class="active">
                            <a href="#link" aria-controls="link" role="tab" data-toggle="tab">{{ trans('texts.link') }}</a>
                        </li>
                        <li role="presentation">
                            <a href="#navigation" aria-controls="navigation" role="tab" data-toggle="tab">{{ trans('texts.navigation') }}</a>
                        </li>
                        <li role="presentation">
                            <a href="#custom_css" aria-controls="custom_css" role="tab" data-toggle="tab">{{ trans('texts.custom_css') }}</a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane active" id="link">
                        <div class="panel-body">

                            @if (Utils::isNinja() && ! Utils::isReseller())
                                {!! Former::inline_radios('domain_id')
                                        ->label(trans('texts.domain'))
                                        ->radios([
                                            'invoiceninja.com' => ['value' => \Domain::INVOICENINJA_COM, 'name' => 'domain_id'],
                                            'invoice.services' => ['value' => \Domain::INVOICE_SERVICES, 'name' => 'domain_id'],
                                        ])->check($account->domain_id)
                                        ->help($account->iframe_url ? 'domain_help_website' : 'domain_help') !!}
                            @endif

                            {!! Former::inline_radios('custom_invoice_link')
                                    ->onchange('onCustomLinkChange()')
                                    ->label(trans('texts.customize'))
                                    ->radios([
                                        trans('texts.subdomain') => ['value' => 'subdomain', 'name' => 'custom_link'],
                                        trans('texts.website') => ['value' => 'website', 'name' => 'custom_link'],
                                    ])->check($account->iframe_url ? 'website' : 'subdomain') !!}
                            {{ Former::setOption('capitalize_translations', false) }}

                            {!! Former::text('subdomain')
                                        ->placeholder(Utils::isNinja() ? 'app' : trans('texts.www'))
                                        ->onchange('onSubdomainChange()')
                                        ->addGroupClass('subdomain')
                                        ->label(' ')
                                        ->help(trans('texts.subdomain_help')) !!}

                            {!! Former::text('iframe_url')
                                        ->placeholder('https://www.example.com/invoice')
                                        ->appendIcon('question-sign')
                                        ->addGroupClass('iframe_url')
                                        ->label(' ')
                                        ->help(trans('texts.subdomain_help')) !!}

                            {!! Former::plaintext('preview')
                                        ->value($account->getSampleLink()) !!}

                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="navigation">
                        <div class="panel-body">

                            {!! Former::checkbox('enable_client_portal')
                                ->text(trans('texts.enable'))
                                ->help(trans('texts.enable_client_portal_help'))
                                ->value(1) !!}


                            {!! Former::checkbox('enable_client_portal_dashboard')
                                ->text(trans('texts.enable'))
                                ->help(trans('texts.enable_client_portal_dashboard_help'))
                                ->value(1) !!}

                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="custom_css">
                        <div class="panel-body">

                            {!! Former::textarea('client_view_css')
                                ->label(trans('texts.custom_css'))
                                ->rows(10)
                                ->raw()
                                ->maxlength(60000)
                                ->style("min-width:100%;max-width:100%;font-family:'Roboto Mono', 'Lucida Console', Monaco, monospace;font-size:14px;'") !!}

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.authorization') !!}</h3>
            </div>
            <div class="panel-body">
                <div role="tabpanel">
                    <ul class="nav nav-tabs" role="tablist" style="border: none">
                        <li role="presentation" class="active"><a href="#password" aria-controls="password" role="tab" data-toggle="tab">{{ trans('texts.password') }}</a></li>
                        <li role="presentation"><a href="#checkbox" aria-controls="checkbox" role="tab" data-toggle="tab">{{ trans('texts.checkbox') }}</a></li>
                        <li role="presentation"><a href="#signature" aria-controls="signature" role="tab" data-toggle="tab">{{ trans('texts.invoice_signature') }}</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane active" id="password">
                        <div class="panel-body">
                          <div class="row">
                            <div class="col-md-10 col-md-offset-1">
                                {!! Former::checkbox('enable_portal_password')
                                    ->text(trans('texts.enable'))
                                    ->help(trans('texts.enable_portal_password_help'))
                                    ->label(trans('texts.enable_portal_password'))
                                    ->value(1) !!}
                            </div>
                            <div class="col-md-10 col-md-offset-1">
                                {!! Former::checkbox('send_portal_password')
                                    ->text(trans('texts.enable'))
                                    ->help(trans('texts.send_portal_password_help'))
                                    ->label(trans('texts.send_portal_password'))
                                    ->value(1) !!}
                            </div>
                        </div>
                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="checkbox">
                        <div class="panel-body">
                          <div class="row">
                            <div class="col-md-10 col-md-offset-1">
                                {!! Former::checkbox('show_accept_invoice_terms')
                                    ->text(trans('texts.enable'))
                                    ->help(trans('texts.show_accept_invoice_terms_help'))
                                    ->label(trans('texts.show_accept_invoice_terms'))
                                    ->value(1) !!}
                            </div>
                            <div class="col-md-10 col-md-offset-1">
                                {!! Former::checkbox('show_accept_quote_terms')
                                    ->text(trans('texts.enable'))
                                    ->help(trans('texts.show_accept_quote_terms_help'))
                                    ->label(trans('texts.show_accept_quote_terms'))
                                    ->value(1) !!}
                            </div>
                        </div>
                        </div>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="signature">
                        <div class="panel-body">
                          <div class="row">
                            <div class="col-md-10 col-md-offset-1">
                                {!! Former::checkbox('require_invoice_signature')
                                    ->text(trans('texts.enable'))
                                    ->help(trans('texts.require_invoice_signature_help'))
                                    ->label(trans('texts.require_invoice_signature'))
                                    ->value(1) !!}
                            </div>
                            <div class="col-md-10 col-md-offset-1">
                                {!! Former::checkbox('require_quote_signature')
                                    ->text(trans('texts.enable'))
                                    ->help(trans('texts.require_quote_signature_help'))
                                    ->label(trans('texts.require_quote_signature'))
                                    ->value(1) !!}
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel panel-default" id="buy_now">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.buy_now_buttons') !!}</h3>
            </div>
            <div class="panel-body">
                <div class="col-md-10 col-md-offset-1">

                    @if (count($gateway_types) && count($products))

                        {!! Former::checkbox('enable_buy_now_buttons')
                            ->text(trans('texts.enable'))
                            ->label(' ')
                            ->help(trans('texts.enable_buy_now_buttons_help'))
                            ->value(1) !!}

                        @if ($account->enable_buy_now_buttons)
                            {!! Former::select('product')
                                ->onchange('updateBuyNowButtons()')
                                ->addOption('', '')
                                ->inlineHelp('buy_now_buttons_warning')
                                ->addGroupClass('product-select') !!}

                            {!! Former::text('redirect_url')
                                    ->onchange('updateBuyNowButtons()')
                                    ->placeholder('https://www.example.com')
                                    ->help('redirect_url_help') !!}

                            {!! Former::checkboxes('client_fields')
                                    ->onchange('updateBuyNowButtons()')
                                    ->checkboxes([
                                        trans('texts.first_name') => ['value' => 'first_name', 'name' => 'first_name'],
                                        trans('texts.last_name') => ['value' => 'last_name', 'name' => 'last_name'],
                                        trans('texts.email') => ['value' => 'email', 'name' => 'email'],
                                    ]) !!}

                            {!! Former::inline_radios('landing_page')
                                    ->onchange('showPaymentTypes();updateBuyNowButtons();')
                                    ->radios([
                                        trans('texts.invoice') => ['value' => 'invoice', 'name' => 'landing_page_type'],
                                        trans('texts.payment') => ['value' => 'payment', 'name' => 'landing_page_type'],
                                    ])->check('invoice') !!}

                            <div id="paymentTypesDiv" style="display:none">
                                {!! Former::select('payment_type')
                                    ->onchange('updateBuyNowButtons()')
                                    ->options($gateway_types) !!}
                            </div>

                            <p>&nbsp;</p>

                            <div role="tabpanel">
                                <ul class="nav nav-tabs" role="tablist" style="border: none">
                                    <li role="presentation" class="active">
                                        <a href="#form" aria-controls="form" role="tab" data-toggle="tab">{{ trans('texts.form') }}</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#buynow_link" aria-controls="buynow_link" role="tab" data-toggle="tab">{{ trans('texts.link') }}</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="tab-content">
                                <div role="tabpanel" class="tab-pane active" id="form">
                                    <textarea id="formTextarea" class="form-control" rows="4" readonly></textarea>
                                </div>
                                <div role="tabpanel" class="tab-pane" id="buynow_link">
                                    <textarea id="linkTextarea" class="form-control" rows="4" readonly></textarea>
                                </div>
                            </div>

                        @endif

                    @else

                        <center style="font-size:16px;color:#888888;">
                            {{ trans('texts.buy_now_buttons_disabled') }}
                        </center>

                    @endif

                </div>
            </div>
        </div>
    </div>
</div>


<center>
	{!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
</center>

{!! Former::close() !!}


<div class="modal fade" id="iframeHelpModal" tabindex="-1" role="dialog" aria-labelledby="iframeHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="min-width:150px">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="iframeHelpModalLabel">{{ trans('texts.iframe_url') }}</h4>
            </div>

            <div class="modal-body">
                <p>{{ trans('texts.iframe_url_help1') }}</p>
                <pre>&lt;center&gt;
&lt;iframe id="invoiceIFrame" width="100%" height="1200" style="max-width:1000px"&gt;&lt;/iframe&gt;
&lt;center&gt;
&lt;script language="javascript"&gt;
var iframe = document.getElementById('invoiceIFrame');
iframe.src = '{{ rtrim(SITE_URL ,'/') }}/view/'
             + window.location.search.substring(1);
&lt;/script&gt;</pre>
                <p>{{ trans('texts.iframe_url_help2') }}</p>
                <p><b>{{ trans('texts.iframe_url_help3') }}</b></p>
                </div>

            <div class="modal-footer" style="margin-top: 0px">
                <button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
            </div>

        </div>
    </div>
</div>


<script>

    var products = {!! $products !!};

    $(function() {
        var $productSelect = $('select#product');
        for (var i=0; i<products.length; i++) {
            var product = products[i];

            $productSelect.append(new Option(formatMoney(product.cost) + ' - ' + product.product_key, product.public_id));
        }
        $productSelect.combobox();

        fixCheckboxes();
        updateBuyNowButtons();
    })

	$('#enable_portal_password').change(fixCheckboxes);

	function fixCheckboxes() {
		var checked = $('#enable_portal_password').is(':checked');
		$('#send_portal_password').prop('disabled', !checked);
	}

    function showPaymentTypes() {
        var val = $('input[name=landing_page_type]:checked').val()
        if (val == '{{ ENTITY_PAYMENT }}') {
            $('#paymentTypesDiv').fadeIn();
        } else {
            $('#paymentTypesDiv').hide();
        }
    }

    function updateBuyNowButtons() {
        var productId = $('#product').val();
        var landingPage = $('input[name=landing_page_type]:checked').val()
        var paymentType = (landingPage == 'payment') ? '/' + $('#payment_type').val() : '/';
        var redirectUrl = $('#redirect_url').val();

        var form = '';
        var link = '';

        if (productId) {
            var link = '{{ url('/buy_now') }}' + paymentType +
                '?account_key={{ $account->account_key }}' +
                '&product_id=' + productId;

            var form = '<form action="' + link + '" method="post" target="_top">' + "\n";

            @foreach (['first_name', 'last_name', 'email'] as $field)
                if ($('input#{{ $field }}').is(':checked')) {
                    form += '<input type="{{ $field == 'email' ? 'email' : 'text' }}" name="{{ $field }}" placeholder="{{ trans("texts.{$field}") }}" required/>' + "\n";
                    link += '&{{ $field }}=';
                }
            @endforeach

            if (redirectUrl) {
                link += '&redirect_url=' + encodeURIComponent(redirectUrl);
                form += '<input type="hidden" name="redirect_url" value="' + redirectUrl + '"/>' + "\n";
            }

            form += '<input type="submit" value="Buy Now" name="submit"/>' + "\n" + '</form>';
        }

        $('#formTextarea').text(form);
        $('#linkTextarea').text(link);
    }


    function onSubdomainChange() {
        var input = $('#subdomain');
        var val = input.val();
        if (!val) return;
        val = val.replace(/[^a-zA-Z0-9_\-]/g, '').toLowerCase().substring(0, {{ MAX_SUBDOMAIN_LENGTH }});
        input.val(val);
    }

    function onCustomLinkChange() {
        var val = $('input[name=custom_link]:checked').val()
        if (val == 'subdomain') {
            $('.subdomain').show();
            $('.iframe_url').hide();
        } else {
            $('.subdomain').hide();
            $('.iframe_url').show();
        }
    }

    $('.iframe_url .input-group-addon').click(function() {
        $('#iframeHelpModal').modal('show');
    });

    $('.email_design_id .input-group-addon').click(function() {
        $('#designHelpModal').modal('show');
    });

    $(function() {
        onCustomLinkChange();

        $('#subdomain').change(function() {
            $('#iframe_url').val('');
        });
        $('#iframe_url').change(function() {
            $('#subdomain').val('');
        });
    });


</script>

@stop
