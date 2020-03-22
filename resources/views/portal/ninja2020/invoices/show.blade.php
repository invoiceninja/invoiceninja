@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.view_invoice'))

@section('header')
    {{ Breadcrumbs::render('invoices.show', $invoice) }}
@endsection

@section('body')

    @if($invoice->isPayable())
        <form action="{{ route('client.invoices.bulk') }}" method="post">
            @csrf
            <div class="bg-white shadow sm:rounded-lg mb-4" translate>
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Unpaid
                            </h3>
                            <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                                <p>
                                    This invoice is still not paid. Click the button to complete the payment.
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                            <div class="inline-flex rounded-md shadow-sm">
                                <input type="hidden" name="invoices[]" value="{{ $invoice->hashed_id }}">
                                <input type="hidden" name="action" value="payment">
                                <button class="button button-primary">@lang('texts.pay_now')</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endif

    <embed src="{{ asset($invoice->pdf_url()) }}#toolbar=1&navpanes=1&scrollbar=1" type="application/pdf" width="100%"
           height="1180px"/>

@endsection
