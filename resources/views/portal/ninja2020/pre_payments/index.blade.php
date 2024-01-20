@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.pre_payment'))

@push('head')
<style>
    [x-cloak] { display: none; }
</style>
@endpush
@section('body')
    <form action="{{ route('client.pre_payments.process') }}" method="post" id="payment-form" x-ref="paymentform">
    @csrf
    <input type="hidden" name="company_gateway_id" id="company_gateway_id">
    <input type="hidden" name="payment_method_id" id="payment_method_id">
    <input type="hidden" name="signature">

    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-6 md:col-start-2 md:col-span-4">
                <div class="flex justify-end">
                </div>

                <div class="mb-4 overflow-hidden bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">
                            {{ ctrans('texts.payment') }}
                        </h3>
                    </div>

                    @component('portal.ninja2020.components.general.card-element', ['title' =>  ctrans('texts.payment_details')])
                        <textarea name="notes" class="focus:shadow-soft-primary-outline min-h-unset text-sm leading-5.6 ease-soft block h-auto w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 outline-none transition-all placeholder:text-gray-500 focus:border-fuchsia-300 focus:outline-none"></textarea>

                        @if($errors->has('notes'))
                            <p class="mt-2 text-red-900 border-red-300 px-2 py-1 bg-gray-100">{{ $errors->first('notes') }}</p>
                        @endif
                    @endcomponent

                    <input type="hidden" name="minimum_amount" value="{{ $minimum }}">

                    @component('portal.ninja2020.components.general.card-element', ['title' => $title])
                        <input
                        type="text"
                        class="input mt-0 mr-4 relative"
                        name="amount"
                        placeholder=""/>
                        
                        @if($minimum > 0) 
                        <p>{{ ctrans('texts.minimum_required_payment', ['amount' => $minimum_amount])}}</p>
                        @endif
                        
                        @if($errors->has('amount'))
                            <p class="mt-2 text-red-900 border-red-300 px-2 py-1 bg-gray-100">{{ $errors->first('amount') }}</p>
                        @endif
                        
                    @endcomponent

                    @if($allows_recurring)
                    <div x-data="{ show: false }">
                    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.enable_recurring')])
                        <input x-on:click="show = !show" id="is_recurring" aria-describedby="recurring-description" name="is_recurring" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                    @endcomponent

                        <div x-cloak x-show="show">
                            @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.number_of_payments')])
                                <select name="remaining_cycles" class="form-select input w-full bg-white">
                                    <option value="-1">{{ ctrans('texts.pre_payment_indefinitely')}}</option>
                                    @for($i = 1; $i < 60; $i++)
                                    <option value={{$i}}  @if($i == 1) selected @endif>{{$i}}</option>
                                    @endfor
                                </select>
                                <span class="py-2">
                                <label for="remaining_cycles" class="col-form-label text-center col-lg-3 text-gray-900">{{ ctrans ('texts.number_of_payments_helper')}}</label>
                                </span>
                            @endcomponent
                            @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.frequency')])
                                <select name="frequency_id" class="form-select input w-full bg-white">
                                    <option value="1">{{ ctrans('texts.freq_daily') }}</option>
                                    <option value="2">{{ ctrans('texts.freq_weekly') }}</option>
                                    <option value="3">{{ ctrans('texts.freq_two_weeks') }}</option>
                                    <option value="4">{{ ctrans('texts.freq_four_weeks') }}</option>
                                    <option value="5" selected>{{ ctrans('texts.freq_monthly') }}</option>
                                    <option value="6">{{ ctrans('texts.freq_two_months') }}</option>
                                    <option value="7">{{ ctrans('texts.freq_three_months') }}</option>
                                    <option value="8">{{ ctrans('texts.freq_four_months') }}</option>
                                    <option value="9">{{ ctrans('texts.freq_six_months') }}</option>
                                    <option value="10">{{ ctrans('texts.freq_annually') }}</option>
                                    <option value="11">{{ ctrans('texts.freq_two_years') }}</option>
                                    <option value="12">{{ ctrans('texts.freq_three_years') }}</option>
                                </select>
                            @endcomponent
                        </div>

                    </div>

                    @endif

                    <div class="px-4 py-3 bg-gray-50 text-right sm:px-6" x-data="{ buttonDisabled: false }">
                        <button type="button" 
                        class="button button-primary bg-primary" 
                        x-on:click="buttonDisabled = true" 
                        x-bind:disabled="buttonDisabled"
                        @click="$refs.paymentform.submit()"
                        >{{ ctrans('texts.pay_now') }}</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</form>
@endsection