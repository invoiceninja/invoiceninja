@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.payment_methods'))

@section('header')
    <div class="bg-white shadow rounded mb-4" translate>
        <div class="px-4 py-5 sm:p-6">
            <div class="sm:flex sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.payment_methods') }}
                    </h3>
                    <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                        <p translate>
                            {{ ctrans('texts.list_of_payment_methods') }}
                        </p>
                    </div>
                </div>
                <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                    <div class="inline-flex rounded-md shadow-sm">
                        <input type="hidden" name="hashed_ids">
                        <input type="hidden" name="action" value="payment">
                        @if($client->getCreditCardGateway())
                            <a href="{{ route('client.payment_methods.create') }}" class="button button-primary">@lang('texts.add_payment_method')</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('body')
    <div class="flex flex-col">
        @livewire('payment-methods-table', ['client' => $client])
    </div>
@endsection