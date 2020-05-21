@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.pay_now'))

@push('head')
    <meta name="public-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="customer-email" content="{{ $gateway->getCustomerEmail() }}">
    <meta name="currency" content="{{ $currency }}">

    @if($currency == 'BHD' || $currency == 'KWD' || $currency == 'OMR')
        <meta name="value" content="{{ $amount * 1000 }}">
    @else
        <meta name="value" content="{{ $amount * 100 }}">
    @endif

    <script src="{{ asset('js/clients/payments/checkout.js') }}"></script>
@endpush

@section('body')
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
                    <div class="bg-gray-50 px-4 py-5 sm:px-6 ">
                        <form id="payment-form" method="POST" action="{{ route('client.payments.response') }}">
                            @csrf
                            <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
                            
                            <script async src="https://cdn.checkout.com/sandbox/js/checkout.js"></script>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
