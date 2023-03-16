@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.pre_payment'))

@section('body')
    <form action="{{ route('client.pre_payments.process') }}" method="post" id="payment-form" onkeypress="return event.keyCode != 13;">
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


                    @component('portal.ninja2020.components.general.card-element', ['title' => $title])
                        <input
                        type="text"
                        class="input mt-0 mr-4 relative"
                        name="amount"
                        placeholder="{{ $minimum_amount }}"
                        min="{{ $minimum_amount }}"/>
                    
                        @if($errors->has('amount'))
                            <p class="mt-2 text-red-900 border-red-300 px-2 py-1 bg-gray-100">{{ $errors->first('amount') }}</p>
                        @endif
                        
                    @endcomponent

                    

                    <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                        <button class="button button-primary bg-primary">{{ ctrans('texts.pay_now') }}</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</form>
@endsection