@extends('accounts.nav')

@section('content') 
    @parent 

    {!! Former::open($url)->method($method)->rule()->addClass('col-md-8 col-md-offset-2 warn-on-exit') !!} 
    {!! Former::populate($account) !!}

    {!! Former::legend($title) !!}
        
    @if ($accountGateway)
        {!! Former::populateField('gateway_id', $accountGateway->gateway_id) !!}
        {!! Former::populateField('recommendedGateway_id', $accountGateway->gateway_id) !!}
        @if ($config)
            @foreach ($accountGateway->fields as $field => $junk)
                @if (in_array($field, ['solutionType', 'landingPage', 'headerImageUrl', 'brandName']))
                    {{-- do nothing --}}
                @elseif (isset($config->$field))
                    {{ Former::populateField($accountGateway->gateway_id.'_'.$field, $config->$field) }}
                @endif
            @endforeach
        @endif
    @endif
    
    
    {!! Former::select('gateway_id')->label('Select Gateway')->addOption('', '')
        ->dataClass('gateway-dropdown')
        ->fromQuery($gateways, 'name', 'id')
        ->onchange('setFieldsShown()'); !!}

    @foreach ($gateways as $gateway)

        <div id="gateway_{{ $gateway->id }}_div" class='gateway-fields' style="display: none">
            @foreach ($gateway->fields as $field => $details)

                @if (in_array($field, ['solutionType', 'landingPage', 'headerImageUrl', 'brandName']))
                    {{-- do nothing --}}
                @elseif ($field == 'testMode' || $field == 'developerMode') 
                    {{-- Former::checkbox($gateway->id.'_'.$field)->label(Utils::toSpaceCase($field))->text('Enable') --}}              
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

    {!! Former::checkboxes('creditCardTypes[]')->label('Accepted Credit Cards')
            ->checkboxes($creditCardTypes)->class('creditcard-types')
    !!}


    <p/>&nbsp;<p/>

    {!! Former::actions( 
        Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')),
        $countGateways > 0 ? Button::normal(trans('texts.cancel'))->large()->asLinkTo('/company/payments')->appendIcon(Icon::create('remove-circle')) : false) !!}
    {!! Former::close() !!}


    <script type="text/javascript">

    function setFieldsShown() {
        var val = $('#gateway_id').val();
        $('.gateway-fields').hide();
        $('#gateway_' + val + '_div').show();
    }

    function gatewayLink(url) {
        var host = new URL(url).hostname;
        if (host) {
            openUrl(url, '/affiliate/' + host);
        }
    }

    </script>

@stop