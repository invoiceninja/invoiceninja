@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' =>
ctrans('texts.credit_card')])

@section('gateway_head')
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="payment_form">
        @csrf

        <input type="hidden" id="securefieldcode" name="SecuredCardData" value="" />
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="1">
        
        @if (!Request::isSecure())
            <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
        @endif

        <div class="alert alert-failure mb-4" hidden id="errors"></div>

        <!-- This is a generic credit card component utilizing CardJS -->
        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
            {{ ctrans('texts.credit_card') }}
        @endcomponent

        <div id="eway-secure-panel"></div>

        @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-card'])
            {{ ctrans('texts.add_payment_method') }}
        @endcomponent
    </form>
@endsection

@section('gateway_footer')
    <!-- Your JS includes go here -->
<script src="https://secure.ewaypayments.com/scripts/eWAY.min.js" data-init="false"></script>

@include('portal.ninja2020.gateways.eway.includes.credt_card')

<script type="text/javascript">

window.onload = function() {
    eWAY.setupSecureField(groupFieldConfig, securePanelCallback);
};


document
.getElementById('authorize-card')
.addEventListener('click', () => {
    
    saveAndSubmit();

});

</script>
@endsection
