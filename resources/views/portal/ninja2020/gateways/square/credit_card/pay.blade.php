@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="square-appId" content="{{ $gateway->company_gateway->getConfigField('applicationId') }}">
    <meta name="square-locationId" content="{{ $gateway->company_gateway->getConfigField('locationId') }}">
    <meta name="square_contact" content="{{ json_encode($square_contact) }}">
    <meta name="amount" content="{{ $amount }}">
    <meta name="currencyCode" content="{{ $currencyCode }}">
    <meta name="instant-payment" content="yes" />

   <style>
    .loader {
      border-top-color: #3498db;
      -webkit-animation: spinner 1.5s linear infinite;
      animation: spinner 1.5s linear infinite;
    }

    @-webkit-keyframes spinner {
      0% {
        -webkit-transform: rotate(0deg);
      }
      100% {
        -webkit-transform: rotate(360deg);
      }
    }

    @keyframes spinner {
      0% {
        transform: rotate(0deg);
      }
      100% {
        transform: rotate(360deg);
      }
    }

   </style>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
        <input type="hidden" name="sourceId" id="sourceId">
        <input type="hidden" name="verificationToken" id="verificationToken">
        <input type="hidden" name="idempotencyKey" value="{{ \Illuminate\Support\Str::uuid() }}">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <div class="flex flex-col" id="loader">
            <div class="loader ease-linear rounded-full border-4 border-t-4 border-gray-200 h-12 w-12 mb-4"></div>
        </div>

        <ul class="list-none hover:list-disc " id="payment-list">
        @if (count($tokens) > 0)
            @foreach($tokens as $token)
            <li class="py-2 hover:text-blue hover:bg-blue-600">
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token toggle-payment-with-token"
                        />
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }} - {{ $token->meta?->exp_month ?? 'xx' }}/{{ $token->meta?->exp_year ?? 'xx' }}</span>
                </label>
            </li>
            @endforeach
        @endisset

            <li class="py-2 hover:text-blue hover:bg-blue-600">
                <label>
                    <input
                        type="radio"
                        id="toggle-payment-with-credit-card"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer"
                        name="payment-type"
                        checked/>
                    <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
                </label>
            </li>    
        </ul>
        
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="card-container"></div>
        <div id="payment-status-container"></div>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
    @if ($gateway->company_gateway->getConfigField('testMode'))
        <script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
    @else
        <script type="text/javascript" src="https://web.squarecdn.com/v1/square.js"></script>
    @endif

    @vite('resources/js/clients/payments/square-credit-card.js')
@endsection
