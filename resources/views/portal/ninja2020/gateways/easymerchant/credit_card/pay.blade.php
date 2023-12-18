@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' =>
ctrans('texts.credit_card')])

@section('gateway_head')

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    
    <script src="{{ asset('build/public/js/card-js.min.js/card-js.min.js') }}"></script>
    <link href="{{ asset('build/public/css/card-js.min.css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf

        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
    

    <!-- <div class="alert alert-failure mb-4" hidden id="errors"></div> -->

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if (count($tokens) > 0)
            @foreach ($tokens as $token)
                <label class="mr-4">
                    <input type="radio" onclick="toggleCard()" value="{{ $token->token }}" data-token="{{ $token->token }}" name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token" />
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            @endforeach
        @endif

        <label>
            <input type="radio" id="toggle-payment-with-credit-card" class="form-radio cursor-pointer" name="payment-type" onclick="toggleCard()" name="new_card" checked />
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    <div id="toggle-card"> 
        
        @include('portal.ninja2020.gateways.easymerchant.includes.credit_card', ["is_required" => ''])
        
        @component('portal.ninja2020.components.general.card-element', ['title' => "Save Card"])
        <label class="mr-4">
            <input class="form-radio cursor-pointer ml-1" type="radio" value="1" name="save_card">Yes
            <input class="form-radio cursor-pointer ml-1" type="radio" value="0" name="save_card" checked>No
        </label>
        
        @endcomponent
    </div>
    <div class="bg-white px-4 py-5 flex justify-end">
        <button
            type="submit"
            id="{{ $id ?? 'pay-now' }}"
            class="button button-primary bg-primary {{ $class ?? '' }}">
            <span>{{ ctrans('texts.pay_now') }}</span>
        </button>
    </div>
    </form>
@endsection

@section('gateway_footer')

@endsection
<script type="text/javascript">
function toggleCard() {
      var switch_card = document.getElementById('toggle-card');
      var card_number = document.getElementById('card_number');
      var expiration_year = document.getElementById('expiration_year');
      var expiration_month = document.getElementById('expiration_month');
      var card = document.querySelector('input[name="payment-type"]:checked').value;

      if (card === "on") {
        switch_card.style.display = 'block';
        card_number.setAttribute('required', '');
        expiration_month.setAttribute('required', '');
        expiration_year.setAttribute('required', '');
      } else {
        switch_card.style.display = 'none';
        card_number.removeAttribute('required');
        expiration_month.removeAttribute('required');
        expiration_year.removeAttribute('required');
      }
    }
</script>