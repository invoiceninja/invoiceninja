@extends('header')

@section('head')
	@parent

    <style type="text/css">
        label.checkbox-inline {
            padding-left: 0px;
        }

        label.checkbox-inline div {
            padding-left: 20px;
        }
    </style>
@stop

@section('content')
    @parent

    @include('accounts.nav', ['selected' => ACCOUNT_PAYMENTS])
	@include('partials.email_templates')

    <div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{!! trans($title) !!}</h3>
    </div>
    <div class="panel-body form-padding-right">
    {!! Former::open_for_files($url)
			->method($method)
			->addClass('warn-on-exit') !!}

    @if ($accountGateway)
        {!! Former::populateField('primary_gateway_id', $accountGateway->gateway_id) !!}
        {!! Former::populateField('recommendedGateway_id', $accountGateway->gateway_id) !!}
        {!! Former::populateField('show_address', intval($accountGateway->show_address)) !!}
        {!! Former::populateField('show_shipping_address', intval($accountGateway->show_shipping_address)) !!}
        {!! Former::populateField('update_address', intval($accountGateway->update_address)) !!}
        {!! Former::populateField('publishable_key', $accountGateway->getPublishableKey() ? str_repeat('*', strlen($accountGateway->getPublishableKey())) : '') !!}
        {!! Former::populateField('enable_ach', $accountGateway->getAchEnabled() ? 1 : 0) !!}
		{!! Former::populateField('enable_apple_pay', $accountGateway->getApplePayEnabled() ? 1 : 0) !!}
        {!! Former::populateField('enable_sofort', $accountGateway->getSofortEnabled() ? 1 : 0) !!}
        {!! Former::populateField('enable_alipay', $accountGateway->getAlipayEnabled() ? 1 : 0) !!}
        {!! Former::populateField('enable_paypal', $accountGateway->getPayPalEnabled() ? 1 : 0) !!}
        {!! Former::populateField('enable_sepa', $accountGateway->getSepaEnabled() ? 1 : 0) !!}
        {!! Former::populateField('enable_bitcoin', $accountGateway->getBitcoinEnabled() ? 1 : 0) !!}
        {!! Former::populateField('plaid_client_id', $accountGateway->getPlaidClientId() ? str_repeat('*', strlen($accountGateway->getPlaidClientId())) : '') !!}
        {!! Former::populateField('plaid_secret', $accountGateway->getPlaidSecret() ? str_repeat('*', strlen($accountGateway->getPlaidSecret())) : '') !!}
        {!! Former::populateField('plaid_public_key', $accountGateway->getPlaidPublicKey() ? str_repeat('*', strlen($accountGateway->getPlaidPublicKey())) : '') !!}

        @if ($config)
            @foreach ($accountGateway->fields as $field => $junk)
                @if (in_array($field, $hiddenFields))
                    {{-- do nothing --}}
                @elseif (isset($config->$field))
                    {{ Former::populateField($accountGateway->gateway_id.'_'.$field, $config->$field) }}
                @endif
            @endforeach
        @endif
    @else
        {!! Former::populateField('show_address', 1) !!}
        {!! Former::populateField('update_address', 1) !!}
        {!! Former::populateField(GATEWAY_SAGE_PAY_DIRECT . '_referrerId', '2C02C252-0F8A-1B84-E10D-CF933EFCAA99') !!}

        @if (Utils::isNinjaDev())
            @include('accounts.partials.payment_credentials')
        @endif
    @endif

    @if ($accountGateway)
        <div style="display: none">
            {!! Former::text('primary_gateway_id') !!}
        </div>
    @else
        {!! Former::select('primary_gateway_id')
            ->fromQuery($primaryGateways, 'name', 'id')
            ->label(trans('texts.gateway_id'))
            ->onchange('setFieldsShown()')
            ->help(count($secondaryGateways) ? false : 'limited_gateways') !!}

        @if (count($secondaryGateways))
            {!! Former::select('secondary_gateway_id')
                ->fromQuery($secondaryGateways, 'name', 'id')
                ->addGroupClass('secondary-gateway')
                ->label(' ')
                ->onchange('setFieldsShown()') !!}
        @endif
    @endif

	<span id="publishableKey" style="display: none">
		{!! Former::text('publishable_key') !!}
	</span>

    @foreach ($gateways as $gateway)
        <div id="gateway_{{ $gateway->id }}_div" class='gateway-fields' style="display: none">
            @foreach ($gateway->fields as $field => $details)

                @if ($details && (!$accountGateway || !$accountGateway->getConfigField($field)) && !is_array($details) && !is_bool($details))
                    {!! Former::populateField($gateway->id.'_'.$field, $details) !!}
                @endif

                @if (in_array($field, $hiddenFields))
                    {{-- do nothing --}}
                @elseif ($gateway->id == GATEWAY_DWOLLA && ($field == 'key' || $field == 'secret')
                    && isset($_ENV['DWOLLA_KEY']) && isset($_ENV['DWOLLA_SECRET']))
                    {{-- do nothing --}}
                @elseif ($field == 'testMode' || $field == 'developerMode' || $field == 'sandbox')
                    {!! Former::checkbox($gateway->id.'_'.$field)->label(ucwords(Utils::toSpaceCase($field)))->text('enable')->value(1) !!}
                @elseif ($field == 'username' || $field == 'password')
                    {!! Former::text($gateway->id.'_'.$field)->label('API '. ucfirst(Utils::toSpaceCase($field))) !!}
                @elseif ($gateway->isCustom())
					@if ($field == 'text')
                    	{!! Former::textarea($gateway->id.'_'.$field)->label(trans('texts.text'))->rows(6) !!}
					@else
						{!! Former::text($gateway->id.'_'.$field)->label('name')->appendIcon('question-sign')->addGroupClass('custom-text') !!}
					@endif
				@else
                    {!! Former::text($gateway->id.'_'.$field)->label($gateway->id == GATEWAY_STRIPE ? trans('texts.secret_key') : ucwords(Utils::toSpaceCase($field))) !!}
                @endif

            @endforeach

            @if ($gateway->id == GATEWAY_BRAINTREE)
                @if ($account->hasGatewayId(GATEWAY_PAYPAL_EXPRESS))
                    {!! Former::checkbox('enable_paypal')
                        ->label(trans('texts.paypal'))
                        ->text(trans('texts.braintree_enable_paypal'))
                        ->value(null)
                        ->disabled(true)
                        ->help(trans('texts.braintree_paypal_disabled_help')) !!}
                @else
                    {!! Former::checkbox('enable_paypal')
                           ->label(trans('texts.paypal'))
                           ->help(trans('texts.braintree_paypal_help', [
                                'link'=>'<a href="https://articles.braintreepayments.com/guides/paypal/setup-guide" target="_blank">'.
                                    trans('texts.braintree_paypal_help_link_text').'</a>'
                            ]))
                           ->text(trans('texts.braintree_enable_paypal'))
                           ->value(1) !!}
                @endif
			@elseif ($gateway->id == GATEWAY_GOCARDLESS)
				<div class="form-group">
		            <label class="control-label col-lg-4 col-sm-4">{{ trans('texts.webhook_url') }}</label>
		            <div class="col-lg-8 col-sm-8 help-block">
		                <input type="text"  class="form-control" onfocus="$(this).select()" readonly value="{{ URL::to(env('WEBHOOK_PREFIX','').'payment_hook/'.$account->account_key.'/'.GATEWAY_GOCARDLESS) }}">
		                <div class="help-block"><strong>{!! trans('texts.stripe_webhook_help', [
		                'link'=>'<a href="https://manage.gocardless.com/developers" target="_blank">'.trans('texts.gocardless_webhook_help_link_text').'</a>'
		            ]) !!}</strong></div>
		            </div>
		        </div>
            @endif

            @if ($gateway->getHelp())
                <div class="form-group">
                    <label class="control-label col-lg-4 col-sm-4"></label>
                    <div class="col-lg-8 col-sm-8 help-block">
                        {!! $gateway->getHelp() !!}
                    </div>
                </div>
            @endif
        </div>

    @endforeach

    <div class="onsite-fields" style="display:none">
        {!! Former::checkbox('show_address')
                ->label(trans('texts.billing_address'))
                ->text(trans('texts.show_address_help'))
                ->addGroupClass('gateway-option')
                ->value(1) !!}

		{!! Former::checkbox('update_address')
                ->label(' ')
                ->text(trans('texts.update_address_help'))
                ->addGroupClass('gateway-option')
                ->value(1) !!}

        {!! Former::checkbox('show_shipping_address')
                ->label(trans('texts.shipping_address'))
                ->text(trans('texts.show_shipping_address_help'))
                ->addGroupClass('gateway-option')
                ->value(1) !!}

        {!! Former::checkboxes('creditCardTypes[]')
                ->label('accepted_card_logos')
                ->checkboxes($creditCardTypes)
                ->class('creditcard-types')
                ->addGroupClass('gateway-option')
                ->inline()
                ->value(1)
        !!}
        <br/>
    </div>

    @if (!$accountGateway || $accountGateway->gateway_id == GATEWAY_STRIPE)
        <div class="stripe-ach">
			{!! Former::plaintext(' ')->value('<b>' . trans('texts.optional_payment_methods') . '</b>') !!}

            {!! Former::checkbox('enable_ach')
                ->label(trans('texts.ach'))
                ->text(trans('texts.enable_ach'))
                ->value(1) !!}

			{!! Former::checkbox('enable_sofort')
                ->label(trans('texts.sofort'))
                ->text(trans('texts.enable_sofort'))
                ->value(1) !!}

            <!--
            {!! Former::checkbox('enable_sepa')
                ->label('SEPA')
                ->text(trans('texts.enable_sepa'))
                ->value(1) !!}
            -->

			{!! Former::checkbox('enable_apple_pay')
                ->label(trans('texts.apple_pay'))
                ->text(trans('texts.enable_apple_pay'))
				->disabled(Utils::isNinjaProd() && ! $account->subdomain)
				->help((Utils::isNinjaProd() && ! $account->subdomain) ? trans('texts.requires_subdomain', [
					'link' => link_to('/settings/client_portal', trans('texts.subdomain_is_set'), ['target' => '_blank'])
				]) : ($accountGateway && $accountGateway->getApplePayEnabled() && Utils::isRootFolder() && ! $accountGateway->getAppleMerchantId() ? 'verification_file_missing' :
					Utils::isNinjaProd() ? trans('texts.apple_pay_domain', [
						'domain' => $account->subdomain . '.' . APP_DOMAIN, 'link' => link_to('https://dashboard.stripe.com/account/apple_pay', 'Stripe', ['target' => '_blank']),
					]) : ''))
                ->value(1) !!}

			@if (Utils::isRootFolder())
				{!! Former::file('apple_merchant_id')
				 		->label('verification_file')
						->addGroupClass('verification-file') !!}
			@endif

			@if ($accountGateway && $accountGateway->getBitcoinEnabled())
				{!! Former::checkbox('enable_bitcoin')
					->label(trans('texts.bitcoin'))
					->text(trans('texts.enable_bitcoin'))
					->value(1) !!}
			@endif

            {!! Former::checkbox('enable_alipay')
                ->label(trans('texts.alipay'))
                ->text(trans('texts.enable_alipay'))
                ->help(trans('texts.stripe_alipay_help', ['link' => link_to('https://dashboard.stripe.com/account/payments/settings', 'Stripe', ['target' => '_blank'])]))
                ->value(1) !!}

            <div class="stripe-webhook-options">
                <div class="form-group">
                    <label class="control-label col-lg-4 col-sm-4">{{ trans('texts.webhook_url') }}</label>
                    <div class="col-lg-8 col-sm-8 help-block">
                        <input type="text"  class="form-control" onfocus="$(this).select()" readonly value="{{ URL::to(env('WEBHOOK_PREFIX','').'payment_hook/'.$account->account_key.'/'.GATEWAY_STRIPE) }}">
                        <div class="help-block">
							<p>
								{!! trans('texts.stripe_webhook_help', [
	                        		'link'=>'<a href="https://dashboard.stripe.com/account/webhooks" target="_blank">'.trans('texts.stripe_webhook_help_link_text').'</a>'
	                    		]) !!}
							</p>
							<p>
								{{ trans('texts.supported_events') }}
								<ul>
									<li>charge.failed</li>
									<li>charge.succeeded</li>
									<li>charge.refunded</li>
									<li>customer.source.updated</li>
									<li>customer.source.deleted</li>
									<li>source.chargeable</li>
								</ul>
							</p>
						</div>
                    </div>
                </div>
            </div>

            <div class="stripe-ach-options" style="display:none">
                <div class="form-group">
                    <div class="col-sm-8 col-sm-offset-4">
                        <h4>{{trans('texts.plaid')}}</h4>
                        <div class="help-block">{{trans('texts.plaid_optional')}}</div>
                    </div>
                </div>
                {!! Former::text('plaid_client_id')->label(trans('texts.client_id')) !!}
                {!! Former::text('plaid_secret')->label(trans('texts.secret')) !!}
                {!! Former::text('plaid_public_key')->label(trans('texts.public_key'))
                    ->help(trans('texts.plaid_environment_help')) !!}
            </div>
        </div>
    @elseif ($accountGateway && $accountGateway->gateway_id == GATEWAY_WEPAY)
            {!! Former::checkbox('enable_ach')
                        ->label(trans('texts.ach'))
                        ->text(trans('texts.enable_ach'))
                        ->value(1) !!}
    @endif

    </div>
    </div>

    <br/>

    <center>
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/settings/online_payments'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->addClass(['save-button'])->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
    </center>

    {!! Former::close() !!}

    <script type="text/javascript">

    function setFieldsShown() {
        var primaryId = $('#primary_gateway_id').val();
        var secondaryId = $('#secondary_gateway_id').val();

        if (primaryId) {
            $('.secondary-gateway').hide();
        } else {
            $('.secondary-gateway').show();
        }

		@if (! $accountGateway)
			if (primaryId == {{ GATEWAY_WEPAY }}) {
				$('.save-button').prop('disabled', true);
			} else {
				$('.save-button').prop('disabled', false);
			}
		@endif

        var val = primaryId || secondaryId;
        $('.gateway-fields').hide();
        $('#gateway_' + val + '_div').show();

        var gateway = _.findWhere(gateways, {'id': parseInt(val)});
        if (parseInt(gateway.is_offsite)) {
            $('.onsite-fields').hide();
        } else {
            $('.onsite-fields').show();
        }

        if (gateway.id == {{ GATEWAY_STRIPE }}) {
            $('.stripe-ach').show();
        } else {
            $('.stripe-ach').hide();
        }

		$('#publishableKey').toggle([{{ GATEWAY_STRIPE }}, {{ GATEWAY_PAYMILL }}].indexOf(gateway.id) >= 0);
    }

    function gatewayLink(url) {
        var host = new URL(url).hostname;
        if (host) {
            openUrl(url, '/affiliate/' + host);
        }
    }

    function enableUpdateAddress(event) {
        var disabled = ! $('#show_address').is(':checked');
        $('#update_address').prop('disabled', disabled);
        $('label[for=update_address]').css('color', disabled ? '#888' : '#000');
    }

    function updateWebhookShown() {
        var enableAch = $('#enable_ach').is(':checked');
        var enableAlipay = $('#enable_alipay').is(':checked');
        var enableSofort = $('#enable_sofort').is(':checked');
        var enableSepa = $('#enable_sepa').is(':checked');
        var enableBicoin = $('#enable_bitcoin').is(':checked');
		var enableApplePay = $('#enable_apple_pay').is(':checked');
        $('.stripe-webhook-options').toggle(enableAch || enableAlipay || enableSofort || enableSepa || enableBicoin);
        $('.stripe-ach-options').toggle(enableAch && {{ $accountGateway && $accountGateway->getPlaidClientId() ? 'true' : 'false' }});
		$('.verification-file').toggle(enableApplePay);
    }

	$('.custom-text .input-group-addon').click(function() {
		$('#templateHelpModal').modal('show');
	});

    var gateways = {!! Cache::get('gateways') !!};

    $(function() {

        setFieldsShown();
        updateWebhookShown();

        $('#show_address, #show_shipping_address').change(enableUpdateAddress);
        enableUpdateAddress();

        $('#enable_ach, #enable_alipay, #enable_sofort, #enable_sepa, #enable_bitcoin, #enable_apple_pay').change(updateWebhookShown);

        @if (!$accountGateway && count($secondaryGateways))
            $('#primary_gateway_id').append($('<option>', {
                value: '',
                text: "{{ trans('texts.more_options') }}"
            }));
        @endif
    })

    </script>

@stop
