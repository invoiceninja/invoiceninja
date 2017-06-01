@extends('header')

@section('content')
    @parent

    @include('accounts.nav', ['selected' => ACCOUNT_PAYMENTS])

    <div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{!! trans($title) !!}</h3>
    </div>
    <div class="panel-body form-padding-right">
    {!! Former::open($url)->method($method)->rule()->addClass('warn-on-exit') !!}

    @if ($accountGateway)
        {!! Former::populateField('primary_gateway_id', $accountGateway->gateway_id) !!}
        {!! Former::populateField('recommendedGateway_id', $accountGateway->gateway_id) !!}
        {!! Former::populateField('show_address', intval($accountGateway->show_address)) !!}
        {!! Former::populateField('update_address', intval($accountGateway->update_address)) !!}
        {!! Former::populateField('publishable_key', $accountGateway->getPublishableStripeKey() ? str_repeat('*', strlen($accountGateway->getPublishableStripeKey())) : '') !!}
        {!! Former::populateField('enable_ach', $accountGateway->getAchEnabled() ? 1 : 0) !!}
        {!! Former::populateField('enable_paypal', $accountGateway->getPayPalEnabled() ? 1 : 0) !!}
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

    @foreach ($gateways as $gateway)

        <div id="gateway_{{ $gateway->id }}_div" class='gateway-fields' style="display: none">
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
                    {!! Former::checkbox($gateway->id.'_'.$field)->label(ucwords(Utils::toSpaceCase($field)))->text('Enable')->value(1) !!}
                @elseif ($field == 'username' || $field == 'password')
                    {!! Former::text($gateway->id.'_'.$field)->label('API '. ucfirst(Utils::toSpaceCase($field))) !!}
                @elseif ($gateway->isCustom() && $field == 'text')
                    {!! Former::textarea($gateway->id.'_'.$field)->label(trans('texts.text'))->rows(6) !!}
                @else
                    {!! Former::text($gateway->id.'_'.$field)->label($gateway->id == GATEWAY_STRIPE ? trans('texts.secret_key') : ucwords(Utils::toSpaceCase($field))) !!}
                @endif

            @endforeach

            @if ($gateway->id == GATEWAY_STRIPE)
                {!! Former::text('publishable_key') !!}

                <div class="form-group">
                    <label class="control-label col-lg-4 col-sm-4">{{ trans('texts.webhook_url') }}</label>
                    <div class="col-lg-8 col-sm-8 help-block">
                        <input type="text"  class="form-control" onfocus="$(this).select()" readonly value="{{ URL::to(env('WEBHOOK_PREFIX','').'payment_hook/'.$account->account_key.'/'.GATEWAY_STRIPE) }}">
                        <div class="help-block"><strong>{!! trans('texts.stripe_webhook_help', [
                        'link'=>'<a href="https://dashboard.stripe.com/account/webhooks" target="_blank">'.trans('texts.stripe_webhook_help_link_text').'</a>'
                    ]) !!}</strong></div>
                    </div>
                </div>
            @elseif ($gateway->id == GATEWAY_BRAINTREE)
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

        {!! Former::checkboxes('creditCardTypes[]')
                ->label('accepted_card_logos')
                ->checkboxes($creditCardTypes)
                ->class('creditcard-types')
                ->addGroupClass('gateway-option')
                ->value(1)
        !!}
    </div>

    @if (!$accountGateway || $accountGateway->gateway_id == GATEWAY_STRIPE)
        <div class="stripe-ach">
            {!! Former::checkbox('enable_ach')
                ->label(trans('texts.ach'))
                ->text(trans('texts.enable_ach'))
                ->help(trans('texts.stripe_ach_help'))
                ->value(1) !!}
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
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
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

    var gateways = {!! Cache::get('gateways') !!};

    $(function() {

        setFieldsShown();
        enablePlaidSettings();

        $('#show_address').change(enableUpdateAddress);
        enableUpdateAddress();

        $('#enable_ach').change(enablePlaidSettings)

        @if (!$accountGateway && count($secondaryGateways))
            $('#primary_gateway_id').append($('<option>', {
                value: '',
                text: "{{ trans('texts.more_options') }}"
            }));
        @endif
    })

    </script>

@stop
