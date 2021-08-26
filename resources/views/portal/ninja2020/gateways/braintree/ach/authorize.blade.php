@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => 'ACH'])

@section('gateway_head')
    <meta name="client-token" content="{{ $client_token ?? '' }}"/>
@endsection

@section('gateway_content')
    @if(session()->has('ach_error'))
        <div class="alert alert-failure mb-4">
            <p>{{ session('ach_error') }}</p>
        </div>
    @endif

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}"
          method="post" id="server_response">
        @csrf

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_type_id" value="2">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
        <input type="hidden" name="nonce" hidden>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_type')])
        <span class="flex items-center mr-4">
            <input class="form-radio mr-2" type="radio" value="checking" name="account-type" checked>
            <span>{{ __('texts.checking') }}</span>
        </span>
        <span class="flex items-center mt-2">
            <input class="form-radio mr-2" type="radio" value="savings" name="account-type">
            <span>{{ __('texts.savings') }}</span>
        </span>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_type')])
        <span class="flex items-center mr-4">
            <input class="form-radio mr-2" type="radio" value="personal" name="ownership-type" checked>
            <span>{{ __('texts.individual_account') }}</span>
        </span>
        <span class="flex items-center mt-2">
            <input class="form-radio mr-2" type="radio" value="business" name="ownership-type">
            <span>{{ __('texts.company_account') }}</span>
        </span>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
        <input class="input w-full" id="account-holder-name" type="text" placeholder="{{ ctrans('texts.name') }}"
               required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_number')])
        <input class="input w-full" id="account-number" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.routing_number')])
        <input class="input w-full" id="routing-number" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.address1')])
        <input class="input w-full" id="billing-street-address" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.address2')])
        <input class="input w-full" id="billing-extended-address" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.locality')])
        <input class="input w-full" id="billing-locality" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.state')])
        <input class="input w-full" id="billing-region" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.postal_code')])
        <input class="input w-full" id="billing-postal-code" type="text" required>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-bank-account'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="https://js.braintreegateway.com/web/3.81.0/js/client.min.js"></script>
    <script src="https://js.braintreegateway.com/web/3.81.0/js/us-bank-account.min.js"></script>

    <script>
        braintree.client.create({
            authorization: document.querySelector('meta[name="client-token"]')?.content
        }).then(function (clientInstance) {
            return braintree.usBankAccount.create({
                client: clientInstance
            });
        }).then(function (usBankAccountInstance) {
            document
                .getElementById('authorize-bank-account')
                ?.addEventListener('click', (e) => {
                    e.target.parentElement.disabled = true;

                    document.getElementById('errors').hidden = true;
                    document.getElementById('errors').textContent = '';

                    let bankDetails = {
                        accountNumber: document.getElementById('account-number').value,
                        routingNumber: document.getElementById('routing-number').value,
                        accountType: document.querySelector('input[name="account-type"]:checked').value,
                        ownershipType: document.querySelector('input[name="ownership-type"]:checked').value,
                        billingAddress: {
                            streetAddress: document.getElementById('billing-street-address').value,
                            extendedAddress: document.getElementById('billing-extended-address').value,
                            locality: document.getElementById('billing-locality').value,
                            region: document.getElementById('billing-region').value,
                            postalCode: document.getElementById('billing-postal-code').value
                        }
                    }

                    if (bankDetails.ownershipType === 'personal') {
                        let name = document.getElementById('account-holder-name').value.split(' ', 2);

                        bankDetails.firstName = name[0];
                        bankDetails.lastName = name[1];
                    } else {
                        bankDetails.businessName = document.getElementById('account-holder-name').value;
                    }

                    usBankAccountInstance.tokenize({
                        bankDetails,
                        mandateText: 'By clicking ["Checkout"], I authorize Braintree, a service of PayPal, on behalf of [your business name here] (i) to verify my bank account information using bank information and consumer reports and (ii) to debit my bank account.'
                    }).then(function (payload) {
                        document.querySelector('input[name=nonce]').value = payload.nonce;
                        document.getElementById('server_response').submit();
                    })
                        .catch(function (error) {
                            e.target.parentElement.disabled = false;

                            document.getElementById('errors').textContent = `${error.details.originalError.message} ${error.details.originalError.details.originalError[0].message}`;
                            document.getElementById('errors').hidden = false;
                        });
                });
        }).catch(function (err) {
            document.getElementById('errors').textContent = `${error.details.originalError.message} ${error.details.originalError.details.originalError[0].message}`;
            document.getElementById('errors').hidden = false;
        });
    </script>
@endsection
