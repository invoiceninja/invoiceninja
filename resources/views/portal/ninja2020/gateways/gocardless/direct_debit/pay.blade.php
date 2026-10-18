@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Direct Debit', 'card_title' => 'Direct Debit'])

@section('gateway_content')
    <div id="gocardless-direct-debit-payment">
        <div class="alert alert-failure mb-4" hidden id="errors"></div>

        @include('portal.ninja2020.gateways.includes.payment_details')

        <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
            @csrf
            <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
            <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
            <input type="hidden" name="source" value="">
            <input type="hidden" name="amount" value="{{ $amount }}">
            <input type="hidden" name="currency" value="{{ $currency }}">
            <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        </form>

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
            <ul class="payment-method-list">
                @foreach ($tokens as $token)
                    <li class="payment-method-item">
                        <label class="payment-method-label">
                            <input type="radio" data-token="{{ $token->token }}" name="payment-type" class="form-radio cursor-pointer toggle-payment-with-token">
                            <span class="ml-1">{{ App\Models\GatewayType::getAlias($token->gateway_type_id) }} {{ $token->getGatewayAccountName() }}</span>
                        </label>
                    </li>
                @endforeach

                <li class="payment-method-item">
                    <label class="payment-method-label">
                        <input type="radio" id="toggle-payment-with-new-gocardless-account" class="form-radio cursor-pointer" name="payment-type" @checked(count($tokens) === 0)>
                        <span class="ml-1">{{ ctrans('texts.new_bank_account') }}</span>
                    </label>
                </li>
            </ul>
        @endcomponent

        <div id="gocardless-payment-action">
            @include('portal.ninja2020.gateways.includes.pay_now')
        </div>
    </div>
@endsection

@push('footer')
    <script>
        const root = document.getElementById('gocardless-direct-debit-payment');
        const source = root.querySelector('input[name=source]');

        Array.from(root.getElementsByClassName('toggle-payment-with-token')).forEach((element) => {
            element.addEventListener('click', (event) => {
                source.value = event.target.dataset.token;
            });
        });

        root.querySelector('#toggle-payment-with-new-gocardless-account').addEventListener('click', () => {
            if (source) {
                source.value = '';
            }

        });

        const payNowButton = root.querySelector('#pay-now');

        if (payNowButton) {
            payNowButton.addEventListener('click', (event) => {
                const button = event.currentTarget;
                button.disabled = true;
                button.querySelector('svg').classList.remove('hidden');
                button.querySelector('span').classList.add('hidden');
                root.querySelector('#server-response').submit();
            });
        }

        root.querySelector('input[name="payment-type"]')?.click();
    </script>
@endpush
