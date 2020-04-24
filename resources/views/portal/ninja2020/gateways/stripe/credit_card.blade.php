@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.pay_now'))

@push('head')
    <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="using-token" content="{{ boolval($token) }}">
    <meta name="turbolinks-visit-control" content="reload">
@endpush

@section('header')
    {{ Breadcrumbs::render('invoices.pay_now') }}
@endsection

@section('body')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        @foreach($invoices as $invoice)
            <input type="hidden" name="hashed_ids[]" value="{{ $invoice->hashed_id }}">
        @endforeach
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
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
                        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500" translate>
                            {{ ctrans('texts.complete_your_payment') }}
                        </p>
                    </div>
                    <div>
                        <dl>
                            @if($token)
                                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
                                    <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                                        {{ ctrans('texts.credit_card') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        {{ strtoupper($token->meta->brand) }} - **** {{ $token->meta->last4 }}
                                    </dd>
                                </div>
                                <div class="bg-white px-4 py-5 flex justify-end">
                                    <button
                                        type="button"
                                        data-secret="{{ $intent->client_secret }}"
                                        data-token="{{ $token->token }}"
                                        id="pay-now-with-token"
                                        class="button button-primary">
                                        {{ ctrans('texts.pay_now') }}
                                    </button>
                                </div>
                            @else
                                <div
                                    class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
                                    <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                                        {{ ctrans('texts.name') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        <input class="input" id="cardholder-name" type="text"
                                               placeholder="{{ ctrans('texts.name') }}">
                                    </dd>
                                </div>
                                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.credit_card') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        <div id="card-element"></div>
                                    </dd>
                                </div>
                                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.token_billing_checkbox') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        <input type="checkbox" class="form-check" name="token-billing-checkbox"/>
                                    </dd>
                                </div>
                                <div class="bg-white px-4 py-5 flex justify-end">
                                    <button
                                        type="button"
                                        id="pay-now"
                                        data-secret="{{ $intent->client_secret }}"
                                        class="button button-primary">
                                        {{ ctrans('texts.pay_now') }}
                                    </button>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('footer')
    <script src="https://js.stripe.com/v3/"></script>
    <script src="{{ asset('js/clients/payments/process.js') }}"></script>
@endpush
