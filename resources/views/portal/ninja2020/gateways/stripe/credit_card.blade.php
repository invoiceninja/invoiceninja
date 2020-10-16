@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.pay_now'))

@push('head')
    <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="using-token" content="{{ boolval($token) }}">
@endpush

@section('body')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="payment_hash" value="{{$payment_hash}}">

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
                        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
                            {{ ctrans('texts.complete_your_payment') }}
                        </p>
                    </div>
                    <div>
                        <dl>
                            @include('portal.ninja2020.gateways.includes.payment_details')

                            @if((int)$total['amount_with_fee'] == 0)
                                @include('portal.ninja2020.gateways.stripe.includes.pay_with_credit')
                            @elseif($token)
                                @include('portal.ninja2020.gateways.stripe.includes.pay_with_token')
                            @else
                                @include('portal.ninja2020.gateways.stripe.includes.card_widget')

                                <div class="bg-white px-4 py-5 flex justify-end">
                                    <button type="button" id="pay-now" data-secret="{{ $intent->client_secret }}" class="button button-primary bg-primary">
                                        <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>{{ __('texts.pay_now') }}</span>
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
