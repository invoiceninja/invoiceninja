@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.ach'))

@push('head')
    <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
@endpush

@section('body')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->id }}">
        <input type="hidden" name="gateway_type_id" value="2">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">
    </form>
    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-6 md:col-start-2 md:col-span-4">
                <div class="alert alert-failure mb-4" hidden id="errors"></div>
                @if(session()->has('ach_error'))
                <div class="alert alert-failure mb-4">
                    <p>{{ session('ach_error') }}</p>
                </div>
                @endif
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.ach') }}
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
                            {{ ctrans('texts.authorize_for_future_use') }}. {{ ctrans('texts.ach_verification_delay_help') }}
                        </p>
                    </div>
                    <div>
                        <dl>
                            <form action="#" method="post" id="token-form">
                                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
                                    <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                                        {{ ctrans('texts.account_holder_type') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2 flex">
                                        <span class="flex items-center mr-4">
                                            <input class="form-radio mr-2" type="radio" value="individual" name="account-holder-type" checked>
                                            <span>{{ __('texts.individual_account') }}</span>
                                        </span>
                                        <span class="flex items-center">
                                            <input class="form-radio mr-2" type="radio" value="company" name="account-holder-type">
                                            <span>{{ __('texts.company_account') }}</span>
                                        </span>
                                    </dd>
                                </div>
                                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex-col md:flex-row items-center">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.account_holder_name') }}
                                    </dt>
                                    <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        <input class="input w-full" id="account-holder-name" type="text" placeholder="{{ ctrans('texts.name') }}" required>
                                    </dd>
                                </div>
                                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex-col md:flex-row items-center">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.country') }}
                                    </dt>
                                    <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        <select name="countries" id="country" class="form-select input w-full" required>
                                            @foreach($countries as $country)
                                                <option value="{{ $country->iso_3166_2 }}">{{ $country->iso_3166_2 }} ({{ $country->name }})</option>
                                            @endforeach
                                        </select>
                                    </dd>
                                </div>
                                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex-col md:flex-row items-center">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.currency') }}
                                    </dt>
                                    <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        <select name="currencies" id="currency" class="form-select input w-full">
                                            @foreach($currencies as $currency)
                                                <option value="{{ $currency->code }}">{{ $currency->code }} ({{ $currency->name }})</option>
                                            @endforeach
                                        </select>
                                    </dd>
                                </div>
                                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex-col md:flex-row items-center">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.routing_number') }}
                                    </dt>
                                    <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        <input class="input w-full" id="routing-number" type="text" required>
                                    </dd>
                                </div>
                                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex-col md:flex-row items-center">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.account_number') }}
                                    </dt>
                                    <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        <input class="input w-full" id="account-number" type="text" required>
                                    </dd>
                                </div>
                                <div class="bg-gray-50 px-4 py-5 sm:grid  sm:px-6 flex-col md:flex-row items-center">
                                    <span class="text-sm leading-5 font-medium text-gray-500">
                                        <input type="checkbox" class="form-checkbox mr-1" id="accept-terms" required>
                                        <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->user()->company->present()->name, 'email' => auth()->user()->company->email]) }}</label>
                                    </span>
                                </div>
                                <div class="bg-white px-4 py-5 flex justify-end">
                                    <button type="submit" class="button button-primary" id="save-button">
                                        <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>{{ __('texts.save') }}</span>
                                    </button>
                                </div>
                            </form>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('footer')
    <script src="https://js.stripe.com/v3/"></script>
    <script src="{{ asset('js/clients/payment_methods/authorize-ach.js') }}"></script>
@endpush 
