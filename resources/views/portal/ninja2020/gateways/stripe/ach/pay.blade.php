@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => 'ACH'])

@section('gateway_head')
    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    @endif

        <meta name="client_secret" content="{{ $client_secret }}">
        <meta name="mandate_client_secret" content="{{ $mandate_client_secret }}">
        <meta name="viewport" content="width=device-width, minimum-scale=1" />
        <meta name="address-1" content="{{ $gateway->client->address1 }}">
        <meta name="address-2" content="{{ $gateway->client->address2 }}">
        <meta name="city" content="{{ $gateway->client->city }}">
        <meta name="state" content="{{ $gateway->client->state }}">
        <meta name="postal_code" content="{{ $gateway->client->postal_code }}">
        <meta name="country" content="{{ $gateway->client->country?->iso_3166_2 }}">

@endsection

@section('gateway_content')
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="source" value="">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="customer" value="{{ $customer->id }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="client_secret" value="{{ $client_secret }}">
        <input type="hidden" name="gateway_response" id="gateway_response" value="">
        <input type="hidden" name="bank_account_response" id="bank_account_response" value="">
        <input type="hidden" name="setup_intent_id" id="setup_intent_id" value="">
    </form>
    
    @if(count($tokens) > 0)

        @php($hasSelectableToken = collect($tokens)->contains(fn ($token) => ($token->meta->state ?? null) !== 'pending'))

        @include('portal.ninja2020.gateways.includes.payment_details')

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
            @if(count($tokens) > 0)
            <ul class="payment-method-list">
                @foreach($tokens as $token)
                    @php($tokenState = $token->meta->state ?? null)
                    <li class="payment-method-item">
                        <label class="payment-method-label {{ $tokenState === 'pending' ? 'cursor-not-allowed opacity-60' : '' }}">
                            <input
                                type="radio"
                                data-token="{{ $token->hashed_id }}"
                                data-payment-method="{{ $token->token }}"
                                data-state="{{ $tokenState }}"
                                name="payment-type"
                                class="form-radio cursor-pointer disabled:cursor-not-allowed toggle-payment-with-token"
                                @disabled($tokenState === 'pending') />
                            <span class="ml-1">{{ ctrans('texts.bank_transfer') }} (*{{ $token->meta->last4 }})</span>
                            @if($tokenState === 'pending')
                                <span class="ml-2 inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800 ach-token-status">
                                    {{ ctrans('texts.stripe_ach_verifiation_pending') }}
                                </span>
                            @endif
                        </label>
                    </li>
                @endforeach
            </ul>
            @endif
        @endcomponent

        @if($mandate_client_secret)
            <div id="mandate-authorization" hidden>
                @component('portal.ninja2020.components.general.card-element-single')
                    <input type="checkbox" class="form-checkbox mr-1" id="accept-mandate" required>
                    <label for="accept-mandate" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->guard('contact')->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}</label>
                @endcomponent
            </div>
        @endif

        @include('portal.ninja2020.gateways.includes.pay_now', ['disabled' => ! $hasSelectableToken])

    @else

        @component('portal.ninja2020.components.general.card-element-single')
            <input type="checkbox" class="form-checkbox mr-1" id="accept-terms" required>
            <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->guard('contact')->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}</label>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
            <input class="input w-full" id="account-holder-name-field" type="text" placeholder="{{ ctrans('texts.name') }}" value="{{ $gateway->client->present()->first_name() }} {{ $gateway->client->present()->last_name() }}"required>
        @endcomponent
        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.email')])
            <input class="input w-full" id="email-field" type="text" placeholder="{{ ctrans('texts.email') }}" value="{{ $gateway->client->present()->email(); }}" required>
        @endcomponent
        <div class="px-4 py-5 sm:px-6 lg:grid lg:grid-cols-3 lg:gap-4 lg:flex lg:items-center">
            <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                Connect a bank account
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                <button type="button" class="button button-primary bg-primary" id="new-bank" type="button">
                    <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ $slot ?? ctrans('texts.new_bank_account') }}</span>
                </button>
            </dd>
        </div>
    @endif

@endsection

@push('footer')
<script src="https://js.stripe.com/v3/"></script>

<script>

    let payNow = document.getElementById('pay-now');
    const errors = document.getElementById('errors');

    if(payNow)
    {
    
        Array
            .from(document.getElementsByClassName('toggle-payment-with-token'))
            .forEach((element) => element.addEventListener('click', (element) => {
                document.querySelector('input[name=source]').value = element.target.dataset.token;

                const mandateAuthorization = document.getElementById('mandate-authorization');

                if (mandateAuthorization) {
                    mandateAuthorization.hidden = element.target.dataset.state !== 'inactive';
                }
            }));
        payNow.addEventListener('click', function (event) {
            const selectedToken = document.querySelector('input[name="payment-type"]:checked:not(:disabled)');

            if (!selectedToken) {
                event.preventDefault();
                return;
            }

            if (selectedToken?.dataset.state === 'inactive') {
                event.preventDefault();
                renewMandate(selectedToken, payNow);
                return;
            }

            setButtonLoading(payNow, true);
            document.getElementById('server-response').submit();
        });
        
    }

    const first = document.querySelector('input[name="payment-type"]:not(:disabled)');

    if (first) {
        first.click();
    } else if (payNow) {
        payNow.disabled = true;
    }

    const newBank = document.getElementById('new-bank');

    if (newBank) {
        newBank.addEventListener('click', (ev) => {

        if (!document.getElementById('accept-terms').checked) {
                errors.textContent = "You must accept the mandate terms prior to making payment.";
                errors.hidden = false;
                return;
        }

        ev.preventDefault();

        errors.hidden = true;

        const accountHolderNameField = document.getElementById('account-holder-name-field');
        const emailField = document.getElementById('email-field');
        const clientSecret = document.querySelector('meta[name="client_secret"]')?.content;
        const address = billingAddress();

        if (!address) {
            errors.textContent = 'A complete billing address is required to pay by bank account.';
            errors.hidden = false;
            return;
        }

        let newBankButton = document.getElementById('new-bank');
        setButtonLoading(newBankButton, true);

        let stripe;

        try {
            stripe = stripeClient();
        } catch (error) {
            showError(error.message || 'An unexpected error occurred.');
            resetButtons();
            return;
        }

        // Calling this method will open the instant verification dialog.
        stripe.collectBankAccountForPayment({
        clientSecret: clientSecret,
        params: {
          payment_method_type: 'us_bank_account',
          payment_method_data: {
            billing_details: {
              name: accountHolderNameField.value,
              email: emailField.value,
              address,
            },
          },
        },
        expand: ['payment_method'],
        })
        .then(({paymentIntent, error}) => {
            if (error) {
                console.error(error.message);
                showError(error.message);
                resetButtons();
                return;
            }

            if (!paymentIntent) {
                showError('An unexpected error occurred.');
                resetButtons();
                return;
            }

            if (paymentIntent.status === 'requires_payment_method') {
                showError('We were unable to process the payment with this account, please try another one.');
                resetButtons();
                return;
            }

            if (paymentIntent.status === 'requires_confirmation') {
                let bank_account_response = document.getElementById('bank_account_response');
                bank_account_response.value = JSON.stringify(paymentIntent);

                return confirmPayment(stripe, clientSecret);
            }

            showError('We were unable to process this payment.');
            resetButtons();
        })
        .catch((error) => {
            showError(error.message || 'An unexpected error occurred.');
            resetButtons();
        });
        });
    }

    function renewMandate(selectedToken, payNowButton)
    {
        const acceptance = document.getElementById('accept-mandate');

        if (!acceptance?.checked) {
            errors.textContent = 'You must accept the mandate terms prior to making payment.';
            errors.hidden = false;
            return;
        }

        const clientSecret = document.querySelector('meta[name="mandate_client_secret"]')?.content;

        if (!clientSecret) {
            errors.textContent = 'We were unable to renew the bank account authorization.';
            errors.hidden = false;
            return;
        }

        errors.hidden = true;
        setButtonLoading(payNowButton, true);

        let stripe;

        try {
            stripe = stripeClient();
        } catch (error) {
            showError(error.message || 'An unexpected error occurred.');
            setButtonLoading(payNowButton, false);
            return;
        }

        stripe
            .confirmUsBankAccountSetup(clientSecret, {
                payment_method: selectedToken.dataset.paymentMethod,
            })
            .then(({setupIntent, error}) => {
                if (error || setupIntent?.status !== 'succeeded') {
                    errors.textContent = error?.message || 'We were unable to renew the bank account authorization.';
                    errors.hidden = false;
                    setButtonLoading(payNowButton, false);
                    return;
                }

                document.getElementById('setup_intent_id').value = setupIntent.id;
                document.getElementById('server-response').submit();
            })
            .catch((error) => {
                showError(error.message || 'An unexpected error occurred.');
                setButtonLoading(payNowButton, false);
            });
    }

    function stripeClient()
    {
        const publishableKey = document.querySelector('meta[name="stripe-publishable-key"]').content;
        const stripeConnect = document.querySelector('meta[name="stripe-account-id"]')?.content;

        return stripeConnect
            ? Stripe(publishableKey, {stripeAccount: stripeConnect})
            : Stripe(publishableKey);
    }

    function confirmPayment(stripe, clientSecret){
        return stripe.confirmUsBankAccountPayment(clientSecret)
          .then(({paymentIntent, error}) => {
            console.log(paymentIntent);
            if (error) {
                console.error(error.message);
                showError(error.message);
                resetButtons();
                return;
            }

            if (!paymentIntent) {
                showError('An unexpected error occurred.');
                resetButtons();
                return;
            }

            if (paymentIntent.status === "requires_payment_method") {
                showError("We were unable to process the payment with this account, please try another one.");
                resetButtons();
                return;
            }

            if (paymentIntent.status === "processing") {

                let gateway_response = document.getElementById('gateway_response');
                gateway_response.value = JSON.stringify(paymentIntent);
                document.getElementById('server-response').submit();
                return;
            }

            if (paymentIntent.next_action?.type === "verify_with_microdeposits" || paymentIntent.next_action?.type === "requires_source_action") {
                errors.textContent = "You will receive an email with details on how to verify your bank account and process payment.";
                errors.hidden = false;
                document.getElementById('new-bank').style.visibility = 'hidden'

                let gateway_response = document.getElementById('gateway_response');
                gateway_response.value = JSON.stringify(paymentIntent);
                document.getElementById('server-response').submit();
                return;
            }

            showError('We were unable to process this payment.');
            resetButtons();
          })
          .catch((error) => {
              showError(error.message || 'An unexpected error occurred.');
              resetButtons();
          });
    }

    function showError(message)
    {
        errors.textContent = message;
        errors.hidden = false;
    }

    /**
     * Nacha requires a complete billing address on the bank account payment method.
     * Line 2 is optional; every other address field must be present.
     */
    function billingAddress()
    {
        const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content.trim() || '';

        const address = {
            line1: meta('address-1'),
            line2: meta('address-2'),
            city: meta('city'),
            state: meta('state'),
            postal_code: meta('postal_code'),
            country: meta('country').toUpperCase(),
        };

        const requiredFields = ['line1', 'city', 'state', 'postal_code', 'country'];

        if (requiredFields.some((field) => address[field].length === 0) || address.country.length !== 2) {
            return null;
        }

        return Object.fromEntries(Object.entries(address).filter(([, value]) => value.length > 0));
    }

    function resetButtons()
    {
        let newBankButton = document.getElementById('new-bank');
        setButtonLoading(newBankButton, false);
    }

    function setButtonLoading(button, loading)
    {
        button.disabled = loading;
        button.querySelector('svg').classList.toggle('hidden', !loading);
        button.querySelector('span').classList.toggle('hidden', loading);
    }
</script>
@endpush
