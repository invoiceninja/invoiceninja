@extends('accounts.nav')

@section('content') 
    @parent 

    {!! Former::open($url)->method($method)->rule()->addClass('col-md-8 col-md-offset-2 warn-on-exit') !!} 
    {!! Former::populate($account) !!}


    <div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{!! trans($title) !!}</h3>
    </div>
    <div class="panel-body">
        
    @if ($accountGateway)
        {!! Former::populateField('gateway_id', $accountGateway->gateway_id) !!}
        {!! Former::populateField('payment_type_id', $paymentTypeId) !!}
        {!! Former::populateField('recommendedGateway_id', $accountGateway->gateway_id) !!}
        {!! Former::populateField('show_address', intval($accountGateway->show_address)) !!}        
        {!! Former::populateField('update_address', intval($accountGateway->update_address)) !!}

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
    @endif
        
    {!! Former::select('payment_type_id')
        ->options($paymentTypes)
        ->addGroupClass('payment-type-option')
        ->onchange('setPaymentType()') !!}

    {!! Former::select('gateway_id')
        ->dataClass('gateway-dropdown')
        ->addGroupClass('gateway-option')
        ->fromQuery($selectGateways, 'name', 'id')
        ->onchange('setFieldsShown()') !!}

    @foreach ($gateways as $gateway)

        <div id="gateway_{{ $gateway->id }}_div" class='gateway-fields' style="display: none">
            @foreach ($gateway->fields as $field => $details)

                @if (in_array($field, $hiddenFields))
                    {{-- do nothing --}}
                @elseif ($gateway->id == GATEWAY_DWOLLA && ($field == 'key' || $field == 'secret') 
                    && isset($_ENV['DWOLLA_KEY']) && isset($_ENV['DWOLLA_SECRET']))
                    {{-- do nothing --}}
                @elseif ($field == 'testMode' || $field == 'developerMode' || $field == 'sandbox') 
                    {!! Former::checkbox($gateway->id.'_'.$field)->label(Utils::toSpaceCase($field))->text('Enable')->value('true') !!}
                @elseif ($field == 'username' || $field == 'password') 
                    {!! Former::text($gateway->id.'_'.$field)->label('API '. ucfirst(Utils::toSpaceCase($field))) !!}
                @else
                    {!! Former::text($gateway->id.'_'.$field)->label(Utils::toSpaceCase($field)) !!} 
                @endif

            @endforeach

            @if ($gateway->getHelp())
                <div class="form-group">
                    <label class="control-label col-lg-4 col-sm-4"></label>
                    <div class="col-lg-8 col-sm-8 help-block">
                        {!! $gateway->getHelp() !!}
                    </div>
                </div>                  
            @endif

            @if ($gateway->id == GATEWAY_STRIPE)
                {!! Former::select('token_billing_type_id')->options($tokenBillingOptions)->help(trans('texts.token_billing_help')) !!}
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

    </div>
    </div>
    
    <p/>&nbsp;<p/>

    {!! Former::actions( 
        $countGateways > 0 ? Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/company/payments'))->appendIcon(Icon::create('remove-circle')) : false,
        Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))) !!}
    {!! Former::close() !!}


    <script type="text/javascript">

    function setPaymentType() {
        var val = $('#payment_type_id').val();
        if (val == 'PAYMENT_TYPE_CREDIT_CARD') {
            $('.gateway-option').show();
            setFieldsShown();
        } else {
            $('.gateway-option').hide();

            if (val == 'PAYMENT_TYPE_PAYPAL') {
                setFieldsShown({{ GATEWAY_PAYPAL_EXPRESS }});
            } else if (val == 'PAYMENT_TYPE_DWOLLA') {
                setFieldsShown({{ GATEWAY_DWOLLA }});
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

    $(function() {
        setPaymentType();
        @if ($accountGateway)
            $('.payment-type-option').hide();
        @endif

        $('#show_address').change(enableUpdateAddress);
        enableUpdateAddress();
    })

    </script>

@stop