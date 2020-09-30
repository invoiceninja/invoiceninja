@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.ach'))

@section('body')
    @if($token)
        <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
            @csrf
            @foreach($invoices as $invoice)
                <input type="hidden" name="hashed_ids[]" value="{{ $invoice->hashed_id }}">
            @endforeach
            <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
            <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
            <input type="hidden" name="source" value="{{ $token->meta->id }}">
            <input type="hidden" name="amount" value="{{ $amount }}">
            <input type="hidden" name="currency" value="{{ $currency }}">
            <input type="hidden" name="customer" value="{{ $customer->id }}">
        </form>
    @endif

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
                        @if($token)
                            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
                                <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                                    {{ ctrans('texts.payment_type') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ ctrans('texts.ach') }} ({{ ctrans('texts.bank_transfer') }}) (****{{ $token->meta->last4 }})
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
                                <button type="button" id="pay-now" class="button button-primary bg-primary" onclick="document.getElementById('server-response').submit()">
                                    {{ ctrans('texts.pay_now') }}
                                </button>
                            </div>
                        @else
                            <div class="bg-gray-50 px-4 py-5 sm:px-6 flex items-center">
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                   <span>{{ ctrans('texts.bank_account_not_linked') }}</span>
                                   <a class="button button-link text-primary" href="{{ route('client.payment_methods.index') }}">{{ ctrans('texts.add_payment_method') }}</a>
                                </dd>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection