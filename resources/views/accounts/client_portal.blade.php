@extends('header')

@section('head')
@parent

    @include('money_script')

    <link href='https://fonts.googleapis.com/css?family=Roboto+Mono' rel='stylesheet' type='text/css'>

    <style>
    .checkbox-inline input[type="checkbox"] {
        margin-left:-20px !important;
    }
    </style>

@stop

@section('content')
@parent

{!! Former::open_for_files()
->addClass('warn-on-exit') !!}

{!! Former::populateField('enable_client_portal', intval($account->enable_client_portal)) !!}
{!! Former::populateField('enable_client_portal_dashboard', intval($account->enable_client_portal_dashboard)) !!}
{!! Former::populateField('client_view_css', $client_view_css) !!}
{!! Former::populateField('enable_portal_password', intval($enable_portal_password)) !!}
{!! Former::populateField('send_portal_password', intval($send_portal_password)) !!}
{!! Former::populateField('enable_buy_now_buttons', intval($account->enable_buy_now_buttons)) !!}
{!! Former::populateField('show_accept_invoice_terms', intval($account->show_accept_invoice_terms)) !!}
{!! Former::populateField('show_accept_quote_terms', intval($account->show_accept_quote_terms)) !!}
{!! Former::populateField('require_invoice_signature', intval($account->require_invoice_signature)) !!}
{!! Former::populateField('require_quote_signature', intval($account->require_quote_signature)) !!}

@if (!Utils::isNinja() && !Auth::user()->account->hasFeature(FEATURE_WHITE_LABEL))
<div class="alert alert-warning" style="font-size:larger;">
	<center>
		{!! trans('texts.white_label_custom_css', ['price' => WHITE_LABEL_PRICE, 'link'=>'<a href="#" onclick="$(\'#whiteLabelModal\').modal(\'show\');">'.trans('texts.white_label_purchase_link').'</a>']) !!}
	</center>
</div>
@endif

@include('accounts.nav', ['selected' => ACCOUNT_CLIENT_PORTAL])

<div class="row">
    <div class="col-md-12">

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.navigation') !!}</h3>
            </div>
            <div class="panel-body">
                <div class="col-md-10 col-md-offset-1">
                    {!! Former::checkbox('enable_client_portal')
                        ->text(trans('texts.enable'))
                        ->help(trans('texts.enable_client_portal_help')) !!}
                </div>
                <div class="col-md-10 col-md-offset-1">
                    {!! Former::checkbox('enable_client_portal_dashboard')
                        ->text(trans('texts.enable'))
                        ->help(trans('texts.enable_client_portal_dashboard_help')) !!}
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
                                    ->label(trans('texts.enable_portal_password')) !!}
                            </div>
                            <div class="col-md-10 col-md-offset-1">
                                {!! Former::checkbox('send_portal_password')
                                    ->text(trans('texts.enable'))
                                    ->help(trans('texts.send_portal_password_help'))
                                    ->label(trans('texts.send_portal_password')) !!}
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
                                    ->label(trans('texts.show_accept_invoice_terms')) !!}
                            </div>
                            <div class="col-md-10 col-md-offset-1">
                                {!! Former::checkbox('show_accept_quote_terms')
                                    ->text(trans('texts.enable'))
                                    ->help(trans('texts.show_accept_quote_terms_help'))
                                    ->label(trans('texts.show_accept_quote_terms')) !!}
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
                                    ->label(trans('texts.require_invoice_signature')) !!}
                            </div>
                            <div class="col-md-10 col-md-offset-1">
                                {!! Former::checkbox('require_quote_signature')
                                    ->text(trans('texts.enable'))
                                    ->help(trans('texts.require_quote_signature_help'))
                                    ->label(trans('texts.require_quote_signature')) !!}
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
                            ->help(trans('texts.enable_buy_now_buttons_help')) !!}

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
                                        <a href="#link" aria-controls="link" role="tab" data-toggle="tab">{{ trans('texts.link') }}</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="tab-content">
                                <div role="tabpanel" class="tab-pane active" id="form">
                                    <textarea id="formTextarea" class="form-control" rows="4" readonly></textarea>
                                </div>
                                <div role="tabpanel" class="tab-pane" id="link">
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

        @if (Utils::hasFeature(FEATURE_CLIENT_PORTAL_CSS))
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.custom_css') !!}</h3>
            </div>
            <div class="panel-body">
                <div class="col-md-10 col-md-offset-1">
                    {!! Former::textarea('client_view_css')
                    ->label(trans('texts.custom_css'))
                    ->rows(10)
                    ->raw()
                    ->maxlength(60000)
                    ->style("min-width:100%;max-width:100%;font-family:'Roboto Mono', 'Lucida Console', Monaco, monospace;font-size:14px;'") !!}
            </div>
        </div>
        @endif
    </div>
</div>
</div>

<center>
	{!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
</center>

{!! Former::close() !!}

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
        var paymentType = landingPage == 'payment' ? '/' + $('#payment_type').val() : '';
        var redirectUrl = $('#redirect_url').val();

        var form = '';
        var link = '';

        if (productId) {
            var link = '{{ url('/buy_now') }}' + paymentType +
                '?account_key={{ $account->account_key }}' +
                '&product_id=' + productId;

            var form = '<form action="{{ url('/buy_now') }}' + paymentType + '" method="post" target="_top">' + "\n" +
                        '<input type="hidden" name="account_key" value="{{ $account->account_key }}"/>' + "\n" +
                        '<input type="hidden" name="product_id" value="' + productId + '"/>' + "\n";

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



</script>

@stop
