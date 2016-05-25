@extends('header')

@section('content')
    @parent

    @include('accounts.nav', ['selected' => ACCOUNT_PAYMENTS])

    @if(!$accountGateway && WEPAY_CLIENT_ID && !$account->getGatewayByType(PAYMENT_TYPE_CREDIT_CARD) && !$account->getGatewayByType(PAYMENT_TYPE_STRIPE))
        @include('accounts.partials.account_gateway_wepay')
    @endif

    <div id="other-providers">
    <div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{!! trans($title) !!}</h3>
    </div>
    <div class="panel-body form-padding-right">
    {!! Former::open($url)->method($method)->rule()->addClass('warn-on-exit') !!}

    @if ($accountGateway)
        {!! Former::populateField('gateway_id', $accountGateway->gateway_id) !!}
        {!! Former::populateField('payment_type_id', $paymentTypeId) !!}
        {!! Former::populateField('recommendedGateway_id', $accountGateway->gateway_id) !!}
        {!! Former::populateField('show_address', intval($accountGateway->show_address)) !!}
        {!! Former::populateField('update_address', intval($accountGateway->update_address)) !!}
        {!! Former::populateField('publishable_key', $accountGateway->getPublishableStripeKey() ? str_repeat('*', strlen($accountGateway->getPublishableStripeKey())) : '') !!}
        {!! Former::populateField('enable_ach', $accountGateway->getAchEnabled() ? '1' : null) !!}
        {!! Former::populateField('enable_paypal', $accountGateway->getPayPalEnabled() ? '1' : null) !!}
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
        {!! Former::populateField('gateway_id', GATEWAY_STRIPE) !!}
        {!! Former::populateField('show_address', 1) !!}
        {!! Former::populateField('update_address', 1) !!}

        @if (Utils::isNinjaDev())
            {!! Former::populateField('23_apiKey', env('STRIPE_TEST_SECRET_KEY')) !!}
            {!! Former::populateField('publishable_key', env('STRIPE_TEST_PUBLISHABLE_KEY')) !!}
        @endif
    @endif

    {!! Former::select('payment_type_id')
        ->options($paymentTypes)
        ->addGroupClass('payment-type-option')
        ->onchange('setPaymentType()') !!}

    {!! Former::select('gateway_id')
        ->dataClass('gateway-dropdown')
        ->addGroupClass('gateway-option gateway-choice')
        ->fromQuery($selectGateways, 'name', 'id')
        ->onchange('setFieldsShown()') !!}

    @foreach ($gateways as $gateway)

        <div id="gateway_{{ $gateway->id }}_div" class='gateway-fields' style="display: none">
            @if ($gateway->getHelp())
                <div class="form-group">
                    <label class="control-label col-lg-4 col-sm-4"></label>
                    <div class="col-lg-8 col-sm-8 help-block">
                        {!! $gateway->getHelp() !!}
                    </div>
                </div>
            @endif

            @foreach ($gateway->fields as $field => $details)

                @if ($details && !$accountGateway && !is_array($details))
                    {!! Former::populateField($gateway->id.'_'.$field, $details) !!}
                @endif

                @if (in_array($field, $hiddenFields))
                    {{-- do nothing --}}
                @elseif ($gateway->id == GATEWAY_DWOLLA && ($field == 'key' || $field == 'secret')
                    && isset($_ENV['DWOLLA_KEY']) && isset($_ENV['DWOLLA_SECRET']))
                    {{-- do nothing --}}
                @elseif ($field == 'testMode' || $field == 'developerMode' || $field == 'sandbox')
                    {!! Former::checkbox($gateway->id.'_'.$field)->label(ucwords(Utils::toSpaceCase($field)))->text('Enable')->value('true') !!}
                @elseif ($field == 'username' || $field == 'password')
                    {!! Former::text($gateway->id.'_'.$field)->label('API '. ucfirst(Utils::toSpaceCase($field))) !!}
                @else
                    {!! Former::text($gateway->id.'_'.$field)->label($gateway->id == GATEWAY_STRIPE ? trans('texts.secret_key') : ucwords(Utils::toSpaceCase($field))) !!}
                @endif

            @endforeach

            @if ($gateway->id == GATEWAY_STRIPE)
                {!! Former::text('publishable_key') !!}
            @endif

            @if ($gateway->id == GATEWAY_STRIPE)
                <div class="form-group">
                    <label class="control-label col-lg-4 col-sm-4">{{ trans('texts.webhook_url') }}</label>
                    <div class="col-lg-8 col-sm-8 help-block">
                        <input type="text"  class="form-control" onfocus="$(this).select()" readonly value="{{ URL::to(env('WEBHOOK_PREFIX','').'paymenthook/'.$account->account_key.'/'.GATEWAY_STRIPE) }}">
                        <div class="help-block"><strong>{!! trans('texts.stripe_webhook_help', [
                        'link'=>'<a href="https://dashboard.stripe.com/account/webhooks" target="_blank">'.trans('texts.stripe_webhook_help_link_text').'</a>'
                    ]) !!}</strong></div>
                    </div>
                </div>
            @endif

            @if ($gateway->id == GATEWAY_BRAINTREE)
                @if ($account->getGatewayByType(PAYMENT_TYPE_PAYPAL))
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
                           ->text(trans('texts.braintree_enable_paypal')) !!}
                @endif
            @endif
        </div>

    @endforeach

    {!! Former::checkbox('show_address')
            ->label(trans('texts.billing_address'))
            ->text(trans('texts.show_address_help'))
            ->addGroupClass('gateway-option') !!}
    {!! Former::checkbox('update_address')
            ->label(' ')
            ->text(trans('texts.update_address_help'))
            ->addGroupClass('gateway-option') !!}

    {!! Former::checkboxes('creditCardTypes[]')
            ->label('Accepted Credit Cards')
            ->checkboxes($creditCardTypes)
            ->class('creditcard-types')
            ->addGroupClass('gateway-option')
    !!}
    <div class="stripe-ach">
        @if ($account->getGatewayByType(PAYMENT_TYPE_DIRECT_DEBIT))
            {!! Former::checkbox('enable_ach')
                ->label(trans('texts.ach'))
                ->text(trans('texts.enable_ach'))
                ->value(null)
                ->disabled(true)
                ->help(trans('texts.stripe_ach_disabled')) !!}
        @else
        {!! Former::checkbox('enable_ach')
            ->label(trans('texts.ach'))
            ->text(trans('texts.enable_ach'))
            ->help(trans('texts.stripe_ach_help')) !!}
        <div class="stripe-ach-options">
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
        @endif
    </div>
    </div>
    </div>

    <p/>&nbsp;<p/>

    {!! Former::actions(
        $countGateways > 0 ? Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/settings/online_payments'))->appendIcon(Icon::create('remove-circle')) : false,
        Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))) !!}
    {!! Former::close() !!}
    </div>


    <script type="text/javascript">

    function setPaymentType() {
        var val = $('#payment_type_id').val();
        if (val == 'PAYMENT_TYPE_CREDIT_CARD') {
            $('.gateway-option').show();
            $('.stripe-ach').hide();
            setFieldsShown();
        } else {
            $('.gateway-option').hide();
            $('.stripe-ach').hide();

            if (val == 'PAYMENT_TYPE_PAYPAL') {
                setFieldsShown({{ GATEWAY_PAYPAL_EXPRESS }});
            } else if (val == 'PAYMENT_TYPE_DWOLLA') {
                setFieldsShown({{ GATEWAY_DWOLLA }});
            } else if (val == 'PAYMENT_TYPE_DIRECT_DEBIT') {
                setFieldsShown({{ GATEWAY_GOCARDLESS }});
            } else if (val == 'PAYMENT_TYPE_STRIPE') {
                $('.gateway-option:not(.gateway-choice)').show();
                $('.stripe-ach').show();
                setFieldsShown({{ GATEWAY_STRIPE }});
            } else {
                setFieldsShown({{ GATEWAY_BITPAY }});
            }
        }
    }

    function setFieldsShown(val) {
        if (!val) {
            val = $('#gateway_id').val();
        }

        $('.gateway-fields').hide();
        $('#gateway_' + val + '_div').show();
    }

    function gatewayLink(url) {
        var host = new URL(url).hostname;
        if (host) {
            openUrl(url, '/affiliate/' + host);
        }
    }

    function enableUpdateAddress(event) {
        var disabled = !$('#show_address').is(':checked');
        $('#update_address').prop('disabled', disabled);
        $('label[for=update_address]').css('color', disabled ? '#888' : '#000');
        if (disabled) {
            $('#update_address').prop('checked', false);
        } else if (event) {
            $('#update_address').prop('checked', true);
        }
    }

    function enablePlaidSettings() {
        var visible = $('#enable_ach').is(':checked');
        $('.stripe-ach-options').toggle(visible);
    }

    $(function() {
        setPaymentType();
        enablePlaidSettings();
        @if ($accountGateway)
            $('.payment-type-option').hide();
        @endif

        $('#show_address').change(enableUpdateAddress);
        enableUpdateAddress();

        $('#enable_ach').change(enablePlaidSettings)

        $('#show-other-providers').click(function(){
            $(this).hide();
            $('#other-providers').show();
        })
    })

    </script>

@stop
