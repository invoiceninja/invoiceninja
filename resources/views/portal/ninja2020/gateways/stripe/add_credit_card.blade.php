@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.add_credit_card'))

@push('head')
<meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
@endpush

@section('body')
<form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="server_response">
    @csrf
    <input type="hidden" name="company_gateway_id" value="{{ $gateway->gateway_id }}">
    <input type="hidden" name="payment_method_id" value="1">
    <input type="hidden" name="gateway_response" id="gateway_response">
    <input type="hidden" name="is_default" id="is_default">
</form>

<div class="container mx-auto">
    <div class="grid grid-cols-6 gap-4">
        <div class="col-span-6 md:col-start-2 md:col-span-4">
            <div class="alert alert-failure mb-4" hidden id="errors"></div>
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.add_credit_card') }}
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
                        {{ ctrans('texts.authorize_for_future_use') }}
                    </p>
                </div>
                <div>
                    @include('portal.ninja2020.gateways.stripe.includes.card_widget')

                    <div class="bg-white px-4 py-5 flex justify-end">
                        <button type="button" id="card-button" data-secret="{{ $intent->client_secret }}" class="button button-primary bg-primary">
                            <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ __('texts.save') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('footer')
<script src="https://js.stripe.com/v3/"></script>
<script src="{{ asset('js/clients/payment_methods/authorize-stripe-card.js') }}"></script>
@endpush
