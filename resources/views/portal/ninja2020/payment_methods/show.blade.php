@extends('portal.ninja2020.layout.app')
@section('meta_title', ucfirst($payment_method->gateway_type->name))

@section('body')
    <div class="container mx-auto">
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    {{ ctrans("texts.{$payment_method->gateway_type->alias}") }}
                </h3>
                <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500" translate>
                    {{ ctrans('texts.payment_method_details') }}
                </p>
            </div>
            <div>
                <dl>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.payment_type') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ ucfirst($payment_method->gateway_type->name) }}
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.type') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ ucfirst($payment_method->meta->brand) }}
                        </dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.card_number') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            **** {{ ucfirst($payment_method->meta->last4) }}
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.date_created') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ $payment_method->formatDateTimestamp($payment_method->created_at, auth()->user()->client->date_format()) }}
                        </dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.default') }}
                        </dt>
                        <div class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ $payment_method->is_default ? ctrans('texts.yes') : ctrans('texts.no') }}
                        </div>
                    </div>
                    @isset($payment_method->meta->exp_month)
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm leading-5 font-medium text-gray-500">
                                {{ ctrans('texts.expires') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payment_method->meta->exp_month }} / {{ $payment_method->meta->exp_year }}
                            </dd>
                        </div>
                    @endisset
                </dl>
            </div>
        </div>
        <div class="bg-white shadow sm:rounded-lg mb-4 mt-4" translate>
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.remove')}}
                        </h3>
                        <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                            <p>
                                {{ ctrans('texts.permanently_remove_payment_method') }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                        <div class="inline-flex rounded-md shadow-sm" x-data="{ open: false }">
                            <button class="button button-danger" translate @click="open = true">
                                {{ ctrans('texts.remove_payment_method') }}
                            </button>
                            @include('portal.ninja2020.payment_methods.includes.modals.removal')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
