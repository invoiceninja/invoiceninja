@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Bank Transfer', 'card_title' => 'Bank Transfer'])

@section('gateway_head')
    @if($gateway->forte->company_gateway->getConfigField('testMode'))
    <script type="text/javascript" src="https://sandbox.forte.net/api/js/v1"></script>
    @else
    <script type="text/javascript" src="https://api.forte.net/js/v1"></script>
    @endif
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->forte->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{$payment_method_id}}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="dataValue" id="dataValue"/>
        <input type="hidden" name="dataDescriptor" id="dataDescriptor"/>
        <input type="hidden" name="token" id="token"/>
        <input type="hidden" name="store_card" id="store_card"/>

        <div id="forte_errors"></div>

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
            Bank Transfer
        @endcomponent

        @include('portal.ninja2020.gateways.includes.payment_details')

        @component('portal.ninja2020.components.general.card-element', ['title' => 'Pay with Bank Transfer'])
            <input type="hidden" name="payment_token" id="payment_token">
            <div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
                style="display: flex!important; justify-content: center!important;">
                <input class="input w-full" id="routing-number" type="text" placeholder="{{ctrans('texts.routing_number')}}" required>
            </div>
            <div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
                style="display: flex!important; justify-content: center!important;">
                <input class="input w-full" id="account-number" type="text" placeholder="{{ctrans('texts.account_number')}}" required>
            </div>
        @endcomponent
        <div class="bg-white px-4 py-5 flex justify-end">
            <button type="button"
            onclick="submitPay()"
                class="button button-primary bg-primary {{ $class ?? '' }}">
                    <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                <span>{{ $slot ?? ctrans('texts.pay_now') }}</span>
            </button>
        </div>
        <input type="submit" style="display: none" id="form_btn">
    </form>

@endsection

@section('gateway_footer')
    <script>
        function onTokenCreated(params) {
            document.getElementById('payment_token').value=params.onetime_token;
            let button = document.querySelector("#form_btn");
            button.click();
        }
        function onTokenFailed(params) {
            var errors = '<div class="alert alert-failure mb-4"><ul><li>'+ params.response_description +'</li></ul></div>';
            document.getElementById("forte_errors").innerHTML = errors;
        }
        function submitPay(){
            var account_number=document.getElementById('account-number').value;
            var routing_number=document.getElementById('routing-number').value;

            var data = {
               api_login_id: '{{$gateway->forte->company_gateway->getConfigField("apiLoginId")}}',
               account_number: account_number,
               routing_number: routing_number, 
               account_type: "checking",
            }

            forte.createToken(data)
               .success(onTokenCreated)
               .error(onTokenFailed);
            return false;
        }
    </script>
@endsection
