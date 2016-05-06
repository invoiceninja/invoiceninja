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
@foreach ($paymentMethods as $paymentMethod)
<div class="payment_method">
            <span class="payment_method_img_container">
                <img height="22" src="{{URL::to('/images/credit_cards/'.str_replace(' ', '', strtolower($paymentMethod['type']->name).'.png'))}}" alt="{{trans("texts.card_" . str_replace(' ', '', strtolower($paymentMethod['type']->name)))}}">
            </span>
    <span class="payment_method_number">&bull;&bull;&bull;&bull;&bull;{{$paymentMethod['last4']}}</span>

    @if($paymentMethod['type']->id == PAYMENT_TYPE_ACH)
    {{ $paymentMethod['bank_name'] }}
    @if($paymentMethod['status'] == 'new')
    <a href="javasript::void" onclick="completeVerification('{{$paymentMethod['id']}}','{{$paymentMethod['currency']->symbol}}')">({{trans('texts.complete_verification')}})</a>
    @elseif($paymentMethod['status'] == 'verification_failed')
    ({{trans('texts.verification_failed')}})
    @endif
    @else
    {!! trans('texts.card_expiration', array('expires'=>Utils::fromSqlDate($paymentMethod['expiration'], false)->format('m/y'))) !!}
    @endif
    @if($paymentMethod['default'])
    ({{trans('texts.used_for_auto_bill')}})
    @elseif($paymentMethod['type']->id != PAYMENT_TYPE_ACH || $paymentMethod['status'] == 'verified')
    <a href="#" onclick="setDefault('{{$paymentMethod['id']}}')">({{trans('texts.use_for_auto_bill')}})</a>
    @endif
    <a href="javasript::void" class="payment_method_remove" onclick="removePaymentMethod('{{$paymentMethod['id']}}')">&times;</a>
</div>
@endforeach

<center>
    {!! Button::success(strtoupper(trans('texts.add_credit_card')))
    ->asLinkTo(URL::to('/client/paymentmethods/add/'.($gateway->getPaymentType() == PAYMENT_TYPE_STRIPE ? 'stripe_credit_card' : 'credit_card'))) !!}
    @if($gateway->getACHEnabled())
    &nbsp;
    {!! Button::success(strtoupper(trans('texts.add_bank_account')))
    ->asLinkTo(URL::to('/client/paymentmethods/add/stripe_ach')) !!}
    @endif
</center>

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
    }
</script>