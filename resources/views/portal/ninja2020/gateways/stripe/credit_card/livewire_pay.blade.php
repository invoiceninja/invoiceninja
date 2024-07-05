<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4">
    @if($stripe_account_id)
        <meta name="stripe-account-id" content="{{ $stripe_account_id }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $company_gateway->getPublishableKey() }}">
    @endif

    <meta name="stripe-secret" content="{{ $client_secret }}">
    <meta name="only-authorization" content="">
    <meta name="client-postal-code" content="{{ $client->postal_code ?? '' }}">
    <meta name="stripe-require-postal-code" content="{{ $company_gateway->require_postal_code }}">

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card" value="{{ $token_billing_string }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <ul class="list-none">
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
            <li class="py-2 hover:text-white hover:bg-blue-600">
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            </li>
            @endforeach
        @endisset

            <li class="py-2 hover:text-white hover:bg-blue-600">
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

    @include('portal.ninja2020.gateways.stripe.includes.card_widget')
    @include('portal.ninja2020.gateways.includes.pay_now')
    
    
    @assets
    <script src="https://js.stripe.com/v3/"></script>
    @endassets
    
    @script
    <script>
    
    const publishableKey =
        document.querySelector('meta[name="stripe-publishable-key"]')?.content ?? '';

    const secret =
        document.querySelector('meta[name="stripe-secret"]')?.content ?? '';

    const onlyAuthorization =
        document.querySelector('meta[name="only-authorization"]')?.content ?? '';

    const stripeConnect =
        document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';


    var cardElement;
    var stripe;
    var elements;

    function setupStripe() {

        if (stripeConnect) {

            stripe = Stripe(publishableKey, {
                stripeAccount: stripeConnect,
            });

        }
        else {
            stripe = Stripe(publishableKey);
        }

        elements = stripe.elements();

    }

    function createElement() {
        cardElement = elements.create('card', {
            hidePostalCode: document.querySelector('meta[name=stripe-require-postal-code]')?.content === "0",
            value: {
                postalCode: document.querySelector('meta[name=client-postal-code]').content,
            },
            hideIcon: false,
        });

    }

    function mountCardElement() {
        cardElement.mount('#card-element');

    }

    function completePaymentUsingToken() {
        let token = document.querySelector('input[name=token]').value;

        let payNowButton = document.getElementById('pay-now');
        payNowButton = payNowButton;

        payNowButton.disabled = true;

        payNowButton.querySelector('svg').classList.remove('hidden');
        payNowButton.querySelector('span').classList.add('hidden');

        stripe
            .handleCardPayment(secret, {
                payment_method: token,
            })
            .then((result) => {
                if (result.error) {
                    return handleFailure(result.error.message);
                }

                return handleSuccess(result);
            });
    }

    function completePaymentWithoutToken() {
        let payNowButton = document.getElementById('pay-now');
        payNowButton = payNowButton;

        payNowButton.disabled = true;

        payNowButton.querySelector('svg').classList.remove('hidden');
        payNowButton.querySelector('span').classList.add('hidden');

        let cardHolderName = document.getElementById('cardholder-name');

        stripe
            .handleCardPayment(secret, cardElement, {
                payment_method_data: {
                    billing_details: { name: cardHolderName.value },
                },
            })
            .then((result) => {
                if (result.error) {
                    return handleFailure(result.error.message);
                }

                return handleSuccess(result);
            });
    }

    function handleSuccess(result) {
        document.querySelector(
            'input[name="gateway_response"]'
        ).value = JSON.stringify(result.paymentIntent);

        let tokenBillingCheckbox = document.querySelector(
            'input[name="token-billing-checkbox"]:checked'
        );

        if (tokenBillingCheckbox) {
            document.querySelector('input[name="store_card"]').value =
                tokenBillingCheckbox.value;
        }

        document.getElementById('server-response').submit();
    }

    function handleFailure(message) {
        let payNowButton = document.getElementById('pay-now');
        
        let errors = document.getElementById('errors');

        errors.textContent = '';
        errors.textContent = message;
        errors.hidden = false;

        payNowButton.disabled = false;
        payNowButton.querySelector('svg').classList.add('hidden');
        payNowButton.querySelector('span').classList.remove('hidden');
    }

    function handleAuthorization() {
        let cardHolderName = document.getElementById('cardholder-name');

        let payNowButton = document.getElementById('authorize-card');

        payNowButton = payNowButton;
        payNowButton.disabled = true;

        payNowButton.querySelector('svg').classList.remove('hidden');
        payNowButton.querySelector('span').classList.add('hidden');

        stripe
            .handleCardSetup(secret, cardElement, {
                payment_method_data: {
                    billing_details: { name: cardHolderName.value },
                },
            })
            .then((result) => {
                if (result.error) {
                    return handleFailure(result.error.message);
                }

                return handleSuccessfulAuthorization(result);
            });
    }

    function handleSuccessfulAuthorization(result) {
        document.getElementById('gateway_response').value = JSON.stringify(
            result.setupIntent
        );

        document.getElementById('server_response').submit();
    }

        setupStripe();

        if (onlyAuthorization) {
            createElement();
            mountCardElement();

            document
                .getElementById('authorize-card')
                .addEventListener('click', () => {
                    return handleAuthorization();
                });
        } else {
            Array
                .from(document.getElementsByClassName('toggle-payment-with-token'))
                .forEach((element) => element.addEventListener('click', (element) => {
                    document.getElementById('stripe--payment-container').classList.add('hidden');
                    document.getElementById('save-card--container').style.display = 'none';
                    document.querySelector('input[name=token]').value = element.target.dataset.token;
                }));

            document
                .getElementById('toggle-payment-with-credit-card')
                .addEventListener('click', (element) => {
                    document.getElementById('stripe--payment-container').classList.remove('hidden');
                    document.getElementById('save-card--container').style.display = 'grid';
                    document.querySelector('input[name=token]').value = "";
                });

            createElement();
            mountCardElement();

            document
                .getElementById('pay-now')
                .addEventListener('click', () => {

                    try {
                        let tokenInput = document.querySelector('input[name=token]');

                        if (tokenInput.value) {
                            return completePaymentUsingToken();
                        }

                        return completePaymentWithoutToken();
                    } catch (error) {
                        console.log(error.message);
                    }

                });
        }
    



    </script>
    @endscript
</div>