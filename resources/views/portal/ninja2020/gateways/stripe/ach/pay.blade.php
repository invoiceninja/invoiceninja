@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => 'ACH'])

@section('gateway_head')
    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    @endif

        <meta name="client_secret" content="{{ $client_secret }}">
        <meta name="viewport" content="width=device-width, minimum-scale=1" />

@endsection

@section('gateway_content')
    <div class="alert alert-failure mb-4" hidden id="errors"></div>
    
    @if(count($tokens) > 0)

        @include('portal.ninja2020.gateways.includes.payment_details')

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
        </form>

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
            @if(count($tokens) > 0)
                @foreach($tokens as $token)
                    <label class="mr-4">
                        <input
                            type="radio"
                            data-token="{{ $token->hashed_id }}"
                            name="payment-type"
                            class="form-radio cursor-pointer toggle-payment-with-token"/>
                        <span class="ml-1 cursor-pointer">{{ ctrans('texts.bank_transfer') }} (*{{ $token->meta->last4 }})</span>
                    </label>
                @endforeach
            @endisset
        @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')

    @else

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
            <input class="input w-full" id="account-holder-name-field" type="text" placeholder="{{ ctrans('texts.name') }}" value="{{ $gateway->client->present()->first_name() }} {{ $gateway->client->present()->last_name(); }}"required>
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

            <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                <div class="inline-flex rounded-md shadow-sm" x-data="{ open: false }">
                    <button class="button button-danger" @click="open = true" id="open-delete-popup" style="display: none;">
                        {{ ctrans('texts.remove_payment_method') }}
                    </button>

                    <div x-show="open" class="fixed inset-x-0 bottom-0 px-4 pb-4 sm:inset-0 sm:flex sm:items-center sm:justify-center" style="display: none;">
                        <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
                             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                             class="fixed inset-0 transition-opacity">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>

                        <div x-show="open" x-transition:enter="ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                             class="px-4 pt-5 pb-4 overflow-hidden transition-all transform bg-white rounded-lg shadow-xl sm:max-w-lg sm:w-full sm:p-6">
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-red-100 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="w-6 h-6 text-red-600" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                    <h3 class="text-lg font-medium leading-6 text-gray-900" translate>
                                        {{ ctrans('texts.confirmation') }}
                                    </h3>
                                    <div class="mt-2">
                                        <p class="text-sm leading-5 text-gray-500">
                                            
                                            {{ ctrans('texts.ach_authorization', ['company' => auth()->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}

                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                <div class="flex w-full rounded-md shadow-sm sm:ml-3 sm:w-auto">
                                        <button type="submit" onclick="confirmPayment(() => this.disabled = true, 0); return true;" class="button button-danger button-block" dusk="confirm-payment-removal">
                                            {{ ctrans('texts.confirm') }}
                                        </button>
                                </div>
                                <div class="flex w-full mt-3 rounded-md shadow-sm sm:mt-0 sm:w-auto">

                                    <button @click="open = false" type="button" class="button button-secondary button-block">
                                        {{ ctrans('texts.cancel') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    @endif

@endsection




@push('footer')
    <script src="https://js.stripe.com/v3/"></script>

    <script>

            let payNow = document.getElementById('pay-now');

            let stripePaymentIntent = '';

            let stripe;

            let response;

            let publishableKey = document.querySelector('meta[name="stripe-publishable-key"]').content
            let stripeConnect = document.querySelector('meta[name="stripe-account-id"]')?.content
           
            if(stripeConnect){
               stripe = Stripe(publishableKey, { stripeAccount: stripeConnect}); 
            }
            else {
                stripe = Stripe(publishableKey);
            }


            if(payNow)
            {
            
                Array
                    .from(document.getElementsByClassName('toggle-payment-with-token'))
                    .forEach((element) => element.addEventListener('click', (element) => {
                        document.querySelector('input[name=source]').value = element.target.dataset.token;
                    }));

                payNow.addEventListener('click', function () {

                        let payNowButton = document.getElementById('pay-now');
                        payNowButton.disabled = true;
                        payNowButton.querySelector('svg').classList.remove('hidden');
                        payNowButton.querySelector('span').classList.add('hidden');

                document.getElementById('server-response').submit();
                });

            }



        document.getElementById('new-bank').addEventListener('click', (ev) => {

            errors.hidden = true;

            let newBankButton = document.getElementById('new-bank');
                newBankButton.disabled = true;
                newBankButton.querySelector('svg').classList.remove('hidden');
                newBankButton.querySelector('span').classList.add('hidden');


              ev.preventDefault();
              const accountHolderNameField = document.getElementById('account-holder-name-field');
              const emailField = document.getElementById('email-field');
              const clientSecret = document.querySelector('meta[name="client_secret"]')?.content;

              // Calling this method will open the instant verification dialog.
              stripe.collectBankAccountForPayment({
                clientSecret: clientSecret,
                params: {
                  payment_method_type: 'us_bank_account',
                  payment_method_data: {
                    billing_details: {
                      name: accountHolderNameField.value,
                      email: emailField.value,
                    },
                  },
                },
                expand: ['payment_method'],
              })
                .then(({paymentIntent, error}) => {
                    if (error) {
                      console.error(error.message);
                      errors.textContent = error.message;
                      errors.hidden = false;
                      resetButtons();
                      // PaymentMethod collection failed for some reason.
                    } else if (paymentIntent.status === 'requires_payment_method') {
                      // Customer canceled the hosted verification modal. Present them with other
                      // payment method type options.
                    } else if (paymentIntent.status === 'requires_confirmation') {
                      // We collected an account - possibly instantly verified, but possibly
                      // manually-entered. Display payment method details and mandate text
                      // to the customer and confirm the intent once they accept
                      // the mandate.
                      stripePaymentIntent = paymentIntent;

                      showModal(paymentIntent);

                    }
                  });

        });

        function showModal(paymentIntent)
        {
            document.getElementById('open-delete-popup').click();
        }

        function confirmPayment(){

          const clientSecret = document.querySelector('meta[name="client_secret"]')?.content;

            stripe.confirmUsBankAccountPayment(clientSecret)
              .then(({paymentIntent, error}) => {

                console.log(paymentIntent);

                if (error) {
                  console.error(error.message);
                  // The payment failed for some reason.
                } else if (paymentIntent.status === "requires_payment_method") {
                  // Confirmation failed. Attempt again with a different payment method.

                      errors.textContent = error.message;
                      errors.hidden = false;
                      resetButtons();

                } else if (paymentIntent.status === "processing") {
                  // Confirmation succeeded! The account will be debited.
                  // Display a message to customer.

                } else if (paymentIntent.next_action?.type === "verify_with_microdeposits") {
                  // The account needs to be verified via microdeposits.
                  // Display a message to consumer with next steps (consumer waits for
                  // microdeposits, then enters a statement descriptor code on a page sent to them via email).
                }
              }).finally((promise) => {

                console.log(promise);
                console.log("and we are finished")

              });

            

            resetButtons();

            finalize();

        }

        function finalize()
        {

        document.getElementById('server-response').submit();
        
        }

        function resetButtons()
        {
            let newBankButton = document.getElementById('new-bank');
            newBankButton.disabled = false;
            newBankButton.querySelector('svg').classList.add('hidden');
            newBankButton.querySelector('span').classList.remove('hidden');

        }

    </script>
@endpush
