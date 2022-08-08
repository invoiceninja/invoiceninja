@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
@endsection

@section('gateway_content')
    <form action="{{ $payment_endpoint_url }}" method="post" id="server_response">
 

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    <-- If there are existing tokens available these are displayed here for you -->
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
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

    <!-- This include gives the options to save the payment method -->
    @include('portal.ninja2020.gateways.includes.save_card')

    <!-- This include pops up a credit card form -->
    @include('portal.ninja2020.gateways.wepay.includes.credit_card')

    @include('portal.ninja2020.gateways.includes.pay_now')

   </form> 
@endsection

@section('gateway_footer')
<script>

    document.getElementById('pay-now').addEventListener('click', function() {
      document.getElementById('server_response').submit();
    });

</script>
@endsection

