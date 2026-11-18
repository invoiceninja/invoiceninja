<meta name="revolut-order-token" content="{{ $order_token }}">
<meta name="revolut-mode" content="{{ $revolut_mode }}">
<meta name="revolut-cardholder-name" content="{{ $cardholder_name }}">
<meta name="revolut-customer-email" content="{{ $customer_email }}">

<form action="{{ route('client.payments.response') }}" method="post" id="server-response">
    @csrf
    <input type="hidden" name="gateway_response">
    <input type="hidden" name="store_card">
    <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
    <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
    <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
    <input type="hidden" name="token">
</form>

<div class="alert alert-failure mb-4" hidden id="errors"></div>

@component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
    {{ ctrans('texts.credit_card') }}
@endcomponent

@include('portal.ninja2020.gateways.includes.payment_details')

@if(count($tokens ?? []) > 0)
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <ul class="list-none">
            @foreach($tokens as $token)
                <li class="py-1">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="payment-type" class="form-radio cursor-pointer toggle-payment-with-token"
                               data-token="{{ $token->hashed_id }}" {{ $loop->first ? 'checked' : '' }}/>
                        <span class="ml-1">{{ $token->meta?->brand }} **** {{ $token->meta?->last4 }}</span>
                    </label>
                </li>
            @endforeach
            <li class="py-1">
                <label class="flex items-center cursor-pointer">
                    <input type="radio" name="payment-type" id="toggle-payment-with-credit-card" class="form-radio cursor-pointer"/>
                    <span class="ml-1">{{ ctrans('texts.new_card') }}</span>
                </label>
            </li>
        </ul>
    @endcomponent
@endif

@include('portal.ninja2020.gateways.includes.save_card')

<div id="revolut-card-field-container">
    @component('portal.ninja2020.components.general.card-element-single')
        <div id="revolut-card-field" class="rounded border border-gray-300 px-3 py-3"></div>
    @endcomponent
</div>

@include('portal.ninja2020.gateways.includes.pay_now')
