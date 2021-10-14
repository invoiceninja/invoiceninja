@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_head')
    @include('portal.ninja2020.gateways.checkout.credit_card.includes.styles')

    <script src="https://cdn.checkout.com/js/framesv2.min.js"></script>
@endsection

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="checkout--container">
            <form class="xl:flex xl:justify-center" id="payment-form" method="POST" action="#">
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
           constructor() {}

           handle() {
               console.log('It works...');
           }
       }

       new CheckoutCreditCardAuthorization().handle();
    </script>
@endsection
