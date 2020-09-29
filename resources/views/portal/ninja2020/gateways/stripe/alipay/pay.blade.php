@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.alipay'))

@push('head')
    <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="return-url" content="{{ $return_url }}">
    <meta name="currency" content="{{ $currency }}">
    <meta name="amount" content="{{ $stripe_amount }}">
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
                        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
                            {{ ctrans('texts.complete_your_payment') }}
                        </p>
                    </div>
                    <form action="#" method="POST" id="pay-now">
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
                            <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                                {{ ctrans('texts.payment_type') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ ctrans('texts.alipay') }}
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
                            <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                                {{ ctrans('texts.amount') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                               <span class="font-bold">{{ App\Utils\Number::formatMoney($amount, $client) }}</span>
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 flex justify-end">
                            <button class="button button-primary" id="pay-now-button">
                                <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ __('texts.save') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('footer')
    <script src="https://js.stripe.com/v3/"></script>
    <script src="{{ asset('js/clients/payments/alipay.js') }}"></script>
@endpush 