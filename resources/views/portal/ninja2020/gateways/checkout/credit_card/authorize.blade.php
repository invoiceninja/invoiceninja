@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_head')
    <meta name="public-key" content="{{ $gateway->getPublishableKey() }}">

    @include('portal.ninja2020.gateways.checkout.credit_card.includes.styles')

    <script src="https://cdn.checkout.com/js/framesv2.min.js"></script>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server_response">
        @csrf

        <input type="hidden" name="payment_method_id" value="{{ \App\Models\GatewayType::CREDIT_CARD }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="checkout--container">
            <form class="xl:flex xl:justify-center" id="authorization-form" method="POST" action="#">
                <div class="one-liner">
                    <div class="card-frame">
                        <!-- form will be added here -->
                    </div>
                    <!-- add submit button -->
                    <button id="pay-button" disabled>
                        {{ ctrans('texts.add_payment_method') }}
                    </button>
                </div>
                <p class="success-payment-message"></p>
            </form>
        </div>
    @endcomponent
@endsection

@section('gateway_footer')
    <script>
        class CheckoutCreditCardAuthorization {
            constructor() {
                this.button = document.querySelector('#pay-button');
            }

            init() {
                this.frames = Frames.init(
                    document.querySelector('meta[name=public-key]').content
                );
            }

            handle() {
                this.init();

                Frames.addEventHandler(Frames.Events.CARD_VALIDATION_CHANGED, (event) => {
                    this.button.disabled = !Frames.isCardValid();
                });

                Frames.addEventHandler(Frames.Events.CARD_TOKENIZED, (event) => {
                    document.querySelector(
                        'input[name="gateway_response"]'
                    ).value = JSON.stringify(event);

                    document.getElementById('server_response').submit();
                });

                document
                    .querySelector('#authorization-form')
                    .addEventListener('submit', (event) => {
                        this.button.disabled = true;

                        event.preventDefault();
                        Frames.submitCard();
                    });
            }
        }

        new CheckoutCreditCardAuthorization().handle();
    </script>
@endsection
