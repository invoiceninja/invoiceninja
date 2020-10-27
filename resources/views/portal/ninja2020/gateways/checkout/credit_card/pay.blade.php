@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.checkout_com'))

@push('head')
    <meta name="public-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="customer-email" content="{{ $customer_email }}">
    <meta name="value" content="{{ $value }}">
    <meta name="currency" content="{{ $currency }}">
    <meta name="reference" content="{{ $payment_hash }}">

    <script src="{{ asset('js/clients/payments/checkout.com.js') }}"></script>
@endpush

@section('body')
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
        @isset($token)
            <input type="hidden" name="token" value="{{ $token->token }}">
        @endisset
    </form>

    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-6 md:col-start-2 md:col-span-4">
                <div class="alert alert-failure mb-4" hidden id="errors"></div>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.pay_now') }}
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
                            {{ ctrans('texts.complete_your_payment') }}
                        </p>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
                        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                            {{ ctrans('texts.payment_type') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ ctrans('texts.checkout_com') }} ({{ ctrans('texts.credit_card') }})
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.subtotal') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ App\Utils\Number::formatMoney($total['invoice_totals'], $client) }}
                        </dd>
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.gateway_fees') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ App\Utils\Number::formatMoney($total['fee_total'], $client) }}
                        </dd>
                        <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                            {{ ctrans('texts.amount') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            <span class="font-bold">{{ App\Utils\Number::formatMoney($total['amount_with_fee'], $client) }}</span>
                        </dd>
                    </div>
                    @isset($token)
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm leading-5 font-medium text-gray-500">
                                {{ ctrans('texts.card_number') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                **** {{ ucfirst($token->meta->last4) }}
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-5 flex justify-end">
                            <button class="button button-primary bg-primary" onclick="document.getElementById('server-response').submit()">
                                {{ ctrans('texts.pay_now') }}
                            </button>
                        </div>
                    @else
                        <div class="{{ ($gateway->company_gateway->token_billing == 'optin' || $gateway->company_gateway->token_billing == 'optout') ? 'sm:grid' : 'hidden' }} bg-gray-50 px-4 py-5 sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm leading-5 font-medium text-gray-500">
                                {{ ctrans('texts.token_billing_checkbox') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                <label class="mr-4">
                                    <input 
                                        type="radio"
                                        class="form-radio cursor-pointer" 
                                        name="token-billing-checkbox" 
                                        id="proxy_is_default"
                                        value="true"
                                        {{ ($gateway->company_gateway->token_billing == 'always' || $gateway->company_gateway->token_billing == 'optout') ? 'checked' : '' }} />
                                    <span class="ml-1 cursor-pointer">{{ ctrans('texts.yes') }}</span>
                                </label>
                                <label>
                                    <input 
                                        type="radio" 
                                        class="form-radio cursor-pointer" 
                                        name="token-billing-checkbox" 
                                        id="proxy_is_default" 
                                        value="false"
                                        {{ ($gateway->company_gateway->token_billing == 'off' || $gateway->company_gateway->token_billing == 'optin') ? 'checked' : '' }} />
                                    <span class="ml-1 cursor-pointer">{{ ctrans('texts.no') }}</span>
                                </label>
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-5 flex justify-end">
                            <form class="payment-form" method="POST" action="https://merchant.com/successUrl">
                                @if(app()->environment() == 'production')
                                    <script async src="https://cdn.checkout.com/js/checkout.js"></script>
                                @else
                                    <script async src="https://cdn.checkout.com/sandbox/js/checkout.js"></script>
                                @endif
                            </form>
                        </div>
                    @endisset
                </div>
            </div>
        </div>
    </div>
@endsection