@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.verification'))

@section('body')
    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-6 md:col-start-2 md:col-span-4">
                @if(session()->has('error'))
                <div class="alert alert-failure mb-4">{{ session('error') }}</div>
                @endif
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.verification') }}
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
                            {{ ctrans('texts.complete_your_bank_account_verification') }} ({{ ctrans('texts.ach') }}/{{ $token->meta->last4 }})
                        </p>
                        <a href="#" class="button-link text-primary text-sm">{{ __('texts.learn_more') }}</a>
                    </div>
                    <div>
                        <form method="post">
                            @csrf
                            <input type="hidden" name="customer" value="{{ $token->gateway_customer_reference }}">
                            <input type="hidden" name="source" value="{{ $token->meta->id }}">

                            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
                                <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                                    #1 {{ ctrans('texts.amount') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    <input type="text" name="transactions[]" class="w-full input" required>
                                </dd>
                            </div>
                            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 flex items-center">
                                <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                                    #2 {{ ctrans('texts.amount') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    <input type="text" name="transactions[]" class="w-full input" required>
                                </dd>
                            </div>
                            <div class="bg-gray-50 px-4 py-5 flex justify-end">
                                <button id="pay-now" class="button button-primary bg-primary">
                                    {{ ctrans('texts.complete_verification') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection