@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.pay_now'))

@push('head')
    <meta name="show-invoice-terms" content="{{ $settings->show_accept_invoice_terms ? true : false }}">
    <meta name="require-invoice-signature" content="{{ $settings->require_invoice_signature ? true : false }}">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script>
@endpush

@section('body')
    <form action="{{ route('client.payments.process') }}" method="post" id="payment-form">
        @csrf
        @foreach($invoices as $invoice)
            <input type="hidden" name="invoices[]" value="{{ $invoice->hashed_id }}">
        @endforeach
        <input type="hidden" name="company_gateway_id" id="company_gateway_id">
        <input type="hidden" name="payment_method_id" id="payment_method_id">
        <input type="hidden" name="signature">
    </form>
    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-6 md:col-start-2 md:col-span-4">
                <div class="flex justify-end">
                    <div class="flex justify-end mb-2">
                        <!-- Pay now button -->
                        @if(count($payment_methods) > 0)
                            <div x-data="{ open: false }" @keydown.window.escape="open = false" @click.away="open = false"
                                class="relative inline-block text-left">
                                <div>
                                    <div class="rounded-md shadow-sm">
                                        <button @click="open = !open" type="button"
                                                class="inline-flex justify-center w-full rounded-md border border-gray-300 px-4 py-2 bg-white text-sm leading-5 font-medium text-gray-700 hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue active:bg-gray-50 active:text-gray-800 transition ease-in-out duration-150">
                                            {{ ctrans('texts.pay_now') }}
                                            <svg class="-mr-1 ml-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div x-show="open" class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg">
                                    <div class="rounded-md bg-white shadow-xs">
                                        <div class="py-1">
                                            @foreach($payment_methods as $payment_method)
                                                <a data-turbolinks="false" href="#" @click="{ open = false }"
                                                data-company-gateway-id="{{ $payment_method['company_gateway_id'] }}"
                                                data-gateway-type-id="{{ $payment_method['gateway_type_id'] }}"
                                                class="dropdown-gateway-button block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:bg-gray-100 focus:text-gray-900">
                                                    {{ $payment_method['label'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <span class="inline-flex items-center text-sm">
                                <span>{{ ctrans('texts.to_pay_invoices') }} &nbsp;</span>
                                <a class="button-link" href="{{ route('client.payment_methods.index') }}">{{ ctrans('texts.add_payment_method_first') }}.</a>
                            </span>
                        @endif
                    </div>
                </div>

                @foreach($invoices as $invoice)
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-4">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                {{ ctrans('texts.invoice') }}
                                <a class="button-link"
                                   href="{{ route('client.invoice.show', $invoice->hashed_id) }}">
                                    (#{{ $invoice->number }})
                                </a>
                            </h3>
                            <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500" translate>
                            </p>
                        </div>
                        <div>
                            <dl>
                                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.invoice_number') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        {{ $invoice->number }}
                                    </dd>
                                </div>
                                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.due_date') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        {{ $invoice->due_date }}
                                    </dd>
                                </div>
                                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.additional_info') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        @if($invoice->po_number)
                                            {{ $invoice->po_number }}
                                        @elseif($invoice->public_notes)
                                            {{ $invoice->public_notes }}
                                        @else
                                            {{ $invoice->invoice_date}}
                                        @endif
                                    </dd>
                                </div>
                                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.amount') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        {{ App\Utils\Number::formatMoney($invoice->amount, $invoice->client) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @include('portal.ninja2020.invoices.includes.terms')
    @include('portal.ninja2020.invoices.includes.signature')
@endsection

@push('footer')
    <script src="{{ asset('js/clients/invoices/payment.js') }}"></script>
@endpush
