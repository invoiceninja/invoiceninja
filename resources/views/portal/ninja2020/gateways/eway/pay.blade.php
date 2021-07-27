@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card" id="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="token" id="token" value="">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ optional($token->meta)->last4 }}</span>
                </label>
            @endforeach
        @endisset

        <label>
            <input
                type="radio"
                id="toggle-payment-with-credit-card"
                class="form-radio cursor-pointer"
                name="payment-type"
                checked/>
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    <div id="eway-secure-panel">
        
        
    @include('portal.ninja2020.gateways.includes.save_card')
    </div>

<!-- -->

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')

    @include('portal.ninja2020.gateways.eway.includes.credit_card')
<script src="https://secure.ewaypayments.com/scripts/eWAY.min.js" data-init="false"></script>


<script type="text/javascript">

    window.onload = function() {
        eWAY.setupSecureField(groupFieldConfig, securePanelCallback);
    };

    document.getElementById('eway-secure-panel').hidden = true;



            Array
                .from(document.getElementsByClassName('toggle-payment-with-token'))
                .forEach((element) => element.addEventListener('click', (e) => {
                    document.getElementById('save-card--container').style.display = 'none';
                    document.getElementById('eway-secure-panel').style.display = 'none';
                    document.getElementById('token').value = e.target.dataset.token;
                }));

            document
                .getElementById('toggle-payment-with-credit-card')
                .addEventListener('click', (e) => {
                    document.getElementById('save-card--container').style.display = 'grid';
                    document.getElementById('eway-secure-panel').style.display = 'flex';
                    document.getElementById('token').value = null;
                });
</script>

@endsection
