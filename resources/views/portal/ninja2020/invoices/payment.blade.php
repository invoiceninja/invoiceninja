@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.pay_now'))

@section('body')
    <div class="bg-white px-4 py-5 shadow rounded border-b border-gray-200 sm:px-6">
        <div class="-ml-4 -mt-4 flex justify-between items-center flex-wrap sm:flex-no-wrap">
            <div class="ml-4 mt-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    @lang('texts.pay_now')
                </h3>
                <p class="mt-1 text-sm leading-5 text-gray-500">
                    List of invoices waiting for payment.
                </p>
            </div>
            <div class="ml-4 mt-4 flex-shrink-0">
                <div class="inline-flex rounded-md shadow-sm">
                    <button type="button" class="button button-primary">
                        Pay now
                    </button>
                </div>
            </div>
        </div>
        <div id="content" class=""></div>
    </div>
@endsection
