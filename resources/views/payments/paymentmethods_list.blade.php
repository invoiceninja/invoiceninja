<style type="text/css">
    .payment_method_img_container{
        width:37px;
        text-align: center;
        display:inline-block;
        margin-right:10px;
    }

    .payment_method{
        margin:20px 0;
    }

    .payment_method_number{
        margin-right:10px;
        width:65px;
        display:inline-block;
    }
</style>
@if (!empty($braintreeClientToken))
    <script type="text/javascript" src="https://js.braintreegateway.com/js/braintree-2.23.0.min.js"></script>
    <script type="text/javascript" >
        $(function() {
            var paypalLink = $('#add-paypal'),
                    paypalUrl = paypalLink.attr('href'),
                    checkout;
            braintree.setup("{{ $braintreeClientToken }}", "custom", {
                onReady: function (integration) {
                    checkout = integration;
                },
                paypal: {
                    container: "paypal-container",
                    singleUse: false,
                    enableShippingAddress: false,
                    enableBillingAddress: false,
                    headless: true,
                    locale: "{{$client->language?$client->language->locale:$client->account->language->locale}}"
                },
                dataCollector: {
                    paypal: true
                },
                onPaymentMethodReceived: function (obj) {
                    window.location.href = paypalUrl + '/' + encodeURIComponent(obj.nonce)
                }
            });
            paypalLink.click(function(e){
                e.preventDefault();
                checkout.paypal.initAuthFlow();
            })
        });
    </script>
@endif
@if(!empty($paymentMethods))
@foreach ($paymentMethods as $paymentMethod)
<div class="payment_method">
            <span class="payment_method_img_container">
                <img height="22" src="{{URL::to('/images/credit_cards/'.str_replace(' ', '', strtolower($paymentMethod->payment_type->name).'.png'))}}" alt="{{trans("texts.card_" . str_replace(' ', '', strtolower($paymentMethod->payment_type->name)))}}">
            </span>
    @if(!empty($paymentMethod->last4))
    <span class="payment_method_number">&bull;&bull;&bull;&bull;&bull;{{$paymentMethod->last4}}</span>
    @endif
    @if($paymentMethod->payment_type_id == PAYMENT_TYPE_ACH)
        @if($paymentMethod->bank_data)
            {{ $paymentMethod->bank_data }}
        @endif
        @if($paymentMethod->status == PAYMENT_METHOD_STATUS_NEW)
        <a href="javasript::void" onclick="completeVerification('{{$paymentMethod->public_id}}','{{$paymentMethod->currency->symbol}}')">({{trans('texts.complete_verification')}})</a>
        @elseif($paymentMethod->status == PAYMENT_METHOD_STATUS_VERIFICATION_FAILED)
        ({{trans('texts.verification_failed')}})
        @endif
    @elseif($paymentMethod->payment_type_id == PAYMENT_TYPE_ID_PAYPAL)
        {{ $paymentMethod->email }}
    @elseif($paymentMethod->expiration)
        {!! trans('texts.card_expiration', array('expires'=>Utils::fromSqlDate($paymentMethod->expiration, false)->format('m/y'))) !!}
    @endif
    @if($paymentMethod->id == $paymentMethod->account_gateway_token->default_payment_method_id)
        ({{trans('texts.used_for_auto_bill')}})
    @elseif($paymentMethod->payment_type_id != PAYMENT_TYPE_ACH || $paymentMethod->status == PAYMENT_METHOD_STATUS_VERIFIED)
        <a href="#" onclick="setDefault('{{$paymentMethod->public_id}}')">({{trans('texts.use_for_auto_bill')}})</a>
    @endif
    <a href="javasript::void" class="payment_method_remove" onclick="removePaymentMethod('{{$paymentMethod->public_id}}')">&times;</a>
</div>
@endforeach
@endif

@if($gateway->gateway_id != GATEWAY_STRIPE || $gateway->getPublishableStripeKey())
<center>
    {!! Button::success(strtoupper(trans('texts.add_credit_card')))
    ->asLinkTo(URL::to('/client/paymentmethods/add/'.($gateway->getPaymentType() == PAYMENT_TYPE_STRIPE ? 'stripe_credit_card' : 'credit_card'))) !!}
    @if($gateway->getACHEnabled())
    &nbsp;
        {!! Button::success(strtoupper(trans('texts.add_bank_account')))
            ->asLinkTo(URL::to('/client/paymentmethods/add/stripe_ach')) !!}
    @endif
    @if($gateway->getPayPalEnabled())
        &nbsp;
        {!! Button::success(strtoupper(trans('texts.add_paypal_account')))
            ->withAttributes(['id'=>'add-paypal'])
            ->asLinkTo(URL::to('/client/paymentmethods/add/braintree_paypal')) !!}
        <div id="paypal-container"></div>
    @endif
</center>
@endif

<div class="modal fade" id="completeVerificationModal" tabindex="-1" role="dialog" aria-labelledby="completeVerificationModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="min-width:150px">
        <div class="modal-content">
            {!! Former::open('/client/paymentmethods/verify') !!}
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="completeVerificationModalLabel">{{ trans('texts.complete_verification') }}</h4>
            </div>

            <div class="modal-body">
                <div style="display:none">
                    {!! Former::text('source_id') !!}
                </div>
                <p>{{ trans('texts.bank_account_verification_help') }}</p>
                <div class="form-group">
                    <label for="verification1" class="control-label col-sm-5">{{ trans('texts.verification_amount1') }}</label>
                    <div class="col-sm-3">
                        <div class="input-group">
                            <span class="input-group-addon"><span class="payment_method_currenct_symbol"></span>0.</span>
                            <input type="number" min="0" max="99" required class="form-control" id="verification1" name="verification1">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="verification2" class="control-label col-sm-5">{{ trans('texts.verification_amount2') }}</label>
                    <div class="col-sm-3">
                        <div class="input-group">
                            <span class="input-group-addon"><span class="payment_method_currenct_symbol"></span>0.</span>
                            <input type="number" min="0" max="99" required class="form-control" id="verification2" name="verification2">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="margin-top: 0px">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ trans('texts.complete_verification') }}</button>
            </div>
            {!! Former::close() !!}
        </div>
    </div>
</div>

<div class="modal fade" id="removePaymentMethodModal" tabindex="-1" role="dialog" aria-labelledby="removePaymentMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="min-width:150px">
        <div class="modal-content">
            {!! Former::open()->id('removeForm') !!}
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="removePaymentMethodModalLabel">{{ trans('texts.remove_payment_method') }}</h4>
            </div>

            <div class="modal-body">
                <p>{{ trans('texts.confirm_remove_payment_method') }}</p>
            </div>
            <div class="modal-footer" style="margin-top: 0px">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ trans('texts.remove') }}</button>
            </div>
            {!! Former::close() !!}
        </div>
    </div>
</div>
{!! Former::open(URL::to('/client/paymentmethods/default'))->id('defaultSourceForm') !!}
<input type="hidden" name="source" id="default_id">
{!! Former::close() !!}

<script type="text/javascript">

    function completeVerification(sourceId, currencySymbol) {
        $('#source_id').val(sourceId);
        $('.payment_method_currenct_symbol').text(currencySymbol);
        $('#completeVerificationModal').modal('show');
    }

    function removePaymentMethod(sourceId) {
        $('#removeForm').attr('action', '{{ URL::to('/client/paymentmethods/%s/remove') }}'.replace('%s', sourceId))
        $('#removePaymentMethodModal').modal('show');
    }

    function setDefault(sourceId) {
        $('#default_id').val(sourceId);
        $('#defaultSourceForm').submit()
        return false;
    }
</script>