@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.add_credit_card'))

@push('head')
<meta name="authorize-public-key" content="{{ $public_client_id }}">
<meta name="authorize-login-id" content="{{ $api_login_id }}">

<meta name="year-invalid" content="{{ ctrans('texts.year_invalid') }}">
<meta name="month-invalid" content="{{ ctrans('texts.month_invalid') }}">
<meta name="credit-card-invalid" content="{{ ctrans('texts.credit_card_invalid') }}">


<script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
<script src="{{ asset('js/clients/payments/card-js.min.js') }}"></script>
<link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">

@endpush

@section('body')
<form action="{{ route('client.payment_methods.store') }}" method="post" id="server_response">
    @csrf
    <input type="hidden" name="company_gateway_id" value="{{ $gateway->id }}">
    <input type="hidden" name="payment_method_id" value="1">
    <input type="hidden" name="gateway_response" id="gateway_response">
    <input type="hidden" name="is_default" id="is_default">
    <input type="hidden" name="dataValue" id="dataValue" />
    <input type="hidden" name="dataDescriptor" id="dataDescriptor" />
</form>
<div class="container mx-auto">
    <div class="grid grid-cols-6 gap-4">
        <div class="col-span-6 md:col-start-2 md:col-span-3">
            <div class="alert alert-failure mb-4" hidden id="errors"></div>
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.add_credit_card') }}
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500" translate>
                        {{ ctrans('texts.authorize_for_future_use') }}
                    </p>
                </div>
                <div>
                    <dl>
                        <div class="bg-gray-50 px-4 py-5 sm:px-6">
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 flex justify-center">
                                @include('portal.ninja2020.gateways.authorize.credit_card')
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm leading-5 font-medium text-gray-500">
                                {{ ctrans('texts.save_as_default') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                <input type="checkbox" class="form-checkbox cursor-pointer" name="proxy_is_default" id="proxy_is_default" />
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 flex justify-end">
                            <div id="errors"></div>

                            <button type="primary" class="button button-primary" id="card_button">{{ ctrans('texts.add_payment_method') }}</button>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('footer')

@if($gateway->getConfigField('testMode'))
<script src="https://jstest.authorize.net/v1/Accept.js" charset="utf-8"></script>
@else
<script src="https://js.authorize.net/v1/Accept.js" charset="utf-8"></script>
@endif

<script src="{{ asset('js/clients/payment_methods/authorize-authorize-card.js') }}"></script>
@endpush