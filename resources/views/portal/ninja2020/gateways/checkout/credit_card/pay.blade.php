@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_head')
    <meta name="public-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="customer-email" content="{{ $customer_email }}">
    <meta name="value" content="{{ $value }}">
    <meta name="currency" content="{{ $currency }}">
    <meta name="reference" content="{{ $payment_hash }}">

    <style>*, *::after, *::before {
            box-sizing: border-box
        }

        html {
            background-color: #FFF;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif
        }

        #payment-form {
            width: 31.5rem;
            margin: 0 auto
        }

        iframe {
            width: 100%
        }

        .one-liner {
            display: flex;
            flex-direction: column
        }

        #pay-button {
            border: none;
            border-radius: 3px;
            color: #FFF;
            font-weight: 500;
            height: 40px;
            width: 100%;
            background-color: #13395E;
            box-shadow: 0 1px 3px 0 rgba(19, 57, 94, 0.4)
        }

        #pay-button:active {
            background-color: #0B2A49;
            box-shadow: 0 1px 3px 0 rgba(19, 57, 94, 0.4)
        }

        #pay-button:hover {
            background-color: #15406B;
            box-shadow: 0 2px 5px 0 rgba(19, 57, 94, 0.4)
        }

        #pay-button:disabled {
            background-color: #697887;
            box-shadow: none
        }

        #pay-button:not(:disabled) {
            cursor: pointer
        }

        .card-frame {
            border: solid 1px #13395E;
            border-radius: 3px;
            width: 100%;
            margin-bottom: 8px;
            height: 40px;
            box-shadow: 0 1px 3px 0 rgba(19, 57, 94, 0.2)
        }

        .card-frame.frame--rendered {
            opacity: 1
        }

        .card-frame.frame--rendered.frame--focus {
            border: solid 1px #13395E;
            box-shadow: 0 2px 5px 0 rgba(19, 57, 94, 0.15)
        }

        .card-frame.frame--rendered.frame--invalid {
            border: solid 1px #D96830;
            box-shadow: 0 2px 5px 0 rgba(217, 104, 48, 0.15)
        }

        .success-payment-message {
            color: #13395E;
            line-height: 1.4
        }

        .token {
            color: #b35e14;
            font-size: 0.9rem;
            font-family: monospace
        }

        @media screen and (min-width: 31rem) {
            .one-liner {
                flex-direction: row
            }

            .card-frame {
                width: 318px;
                margin-bottom: 0
            }

            #pay-button {
                width: 175px;
                margin-left: 8px
            }
        }</style>

    <script src="https://cdn.checkout.com/js/framesv2.min.js"></script>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="reference" value="{{ $payment_hash }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="value" value="{{ $value }}">
        <input type="hidden" name="raw_value" value="{{ $raw_value }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="pay_with_token" value="false">
        <input type="hidden" name="token" value="">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }} (Checkout.com)
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

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="checkout--container">
            <form id="payment-form" method="POST" action="#">
                <div class="one-liner">
                    <div class="card-frame">
                        <!-- form will be added here -->
                    </div>
                    <!-- add submit button -->
                    <button id="pay-button" disabled>
                        {{ ctrans('texts.pay') }} {{ App\Utils\Number::formatMoney($total['amount_with_fee'], $client) }}
                    </button>
                </div>
                <p class="success-payment-message"></p>
            </form>
        </div>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        <div class="hidden" id="pay-now-with-token--container">
            @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now-with-token'])
        </div>
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="{{ asset('js/clients/payments/checkout-credit-card.js') }}"></script>
@endsection
