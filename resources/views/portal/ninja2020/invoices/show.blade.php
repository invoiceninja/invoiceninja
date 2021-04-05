@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.view_invoice'))

@push('head')
    <meta name="pdf-url" content="{{ $invoice->pdf_file_path() }}">
    <meta name="show-invoice-terms" content="{{ $settings->show_accept_invoice_terms ? true : false }}">
    <meta name="require-invoice-signature" content="{{ $client->company->account->hasFeature(\App\Models\Account::FEATURE_INVOICE_SETTINGS) && $settings->require_invoice_signature }}">
    <script src="{{ asset('js/vendor/pdf.js/pdf.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@2.3.2/dist/signature_pad.min.js"></script>
@endpush

@section('body')

    @if(!$invoice->isPayable() && $client->getSetting('custom_message_paid_invoice'))
        @component('portal.ninja2020.components.message')
            {{ $client->getSetting('custom_message_paid_invoice') }}
        @endcomponent
    @endif

    @if($invoice->isPayable())
        <form action="{{ ($settings->client_portal_allow_under_payment || $settings->client_portal_allow_over_payment) ? route('client.invoices.bulk') : route('client.payments.process') }}" method="post" id="payment-form">
            @csrf
            <input type="hidden" name="invoices[]" value="{{ $invoice->hashed_id }}">
            <input type="hidden" name="action" value="payment">

            <input type="hidden" name="company_gateway_id" id="company_gateway_id">
            <input type="hidden" name="payment_method_id" id="payment_method_id">
            <input type="hidden" name="signature">

            <input type="hidden" name="payable_invoices[0][amount]" value="{{ $invoice->partial > 0 ?  \App\Utils\Number::formatValue($invoice->partial, $invoice->client->currency()) : \App\Utils\Number::formatValue($invoice->balance, $invoice->client->currency()) }}">
            <input type="hidden" name="payable_invoices[0][invoice_id]" value="{{ $invoice->hashed_id }}">

            <div class="bg-white shadow sm:rounded-lg mb-4" translate>
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                {{ ctrans('texts.invoice_number_placeholder', ['invoice' => $invoice->number])}}
                                - {{ ctrans('texts.unpaid') }}
                            </h3>
                        </div>
                        <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                            <div class="inline-flex rounded-md shadow-sm">
                                <input type="hidden" name="invoices[]" value="{{ $invoice->hashed_id }}">
                                <input type="hidden" name="action" value="payment">

                                @if($settings->client_portal_allow_under_payment || $settings->client_portal_allow_over_payment)
                                    <button class="button button-primary bg-primary">{{ ctrans('texts.pay_now') }}</button>
                                @else
                                    @livewire('pay-now-dropdown', ['total' => $invoice->partial > 0 ? $invoice->partial : $invoice->balance])
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @else
        <div class="bg-white shadow sm:rounded-lg mb-4">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.invoice_number_placeholder', ['invoice' => $invoice->number])}}
                            - {{ \App\Models\Invoice::stringStatus($invoice->status_id) }}
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($invoice->documents->count() > 0)
        <div class="bg-white shadow sm:rounded-lg mb-4">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <p class="text-lg leading-6 font-medium text-gray-900">{{ ctrans('texts.attachments') }}:</p>
                        @foreach($invoice->documents as $document)
                            <div class="inline-flex items-center space-x-1">
                                <a href="{{ route('client.documents.show', $document->hashed_id) }}" target="_blank"
                                   class="block text-sm button-link text-primary">{{ Illuminate\Support\Str::limit($document->name, 40) }}</a>

                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round" class="text-primary h-6 w-4">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>

                                @if(!$loop->last)
                                    <span>&mdash;</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="flex items-center justify-between mt-4">
        <section class="flex items-center">
            <div class="items-center" style="display: none" id="pagination-button-container">
                <button class="input-label focus:outline-none hover:text-blue-600 transition ease-in-out duration-300"
                        id="previous-page-button" title="Previous page">
                    <svg class="w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <button class="input-label focus:outline-none hover:text-blue-600 transition ease-in-out duration-300"
                        id="next-page-button" title="Next page">
                    <svg class="w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
            <span class="text-sm text-gray-700 ml-2 lg:hidden">{{ ctrans('texts.page') }}:
                <span id="current-page-container"></span>
                <span>{{ strtolower(ctrans('texts.of')) }}</span>
                <span id="total-page-container"></span>
            </span>
        </section>
        <section class="flex items-center space-x-1">
            <div class="flex items-center mr-4 space-x-1 lg:hidden">
                <span class="text-gray-600 mr-2" id="zoom-level">100%</span>
                <a href="#" id="zoom-in">
                    <svg class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 cursor-pointer"
                         xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        <line x1="11" y1="8" x2="11" y2="14"></line>
                        <line x1="8" y1="11" x2="14" y2="11"></line>
                    </svg>
                </a>
                <a href="#" id="zoom-out">
                    <svg class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 cursor-pointer"
                         xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        <line x1="8" y1="11" x2="14" y2="11"></line>
                    </svg>
                </a>
            </div>
            <div x-data="{ open: false }" @keydown.escape="open = false" @click.away="open = false"
                 class="relative inline-block text-left">
                <div>
                    <button @click="open = !open"
                            class="flex items-center text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                        </svg>
                    </button>
                </div>
                <div x-show="open" x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg">
                    <div class="rounded-md bg-white shadow-xs">
                        <div class="py-1">
                            <a target="_blank" href="?mode=fullscreen"
                               class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:bg-gray-100 focus:text-gray-900">{{ ctrans('texts.open_in_new_tab') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <iframe src="{{ $invoice->pdf_file_path() }}" class="h-screen w-full border-0 hidden lg:block mt-4"></iframe>

    <div class="flex justify-center">
        <canvas id="pdf-placeholder" class="shadow rounded-lg bg-white lg:hidden mt-4 p-4"></canvas>
    </div>

    @include('portal.ninja2020.invoices.includes.terms', ['entities' => [$invoice], 'entity_type' => ctrans('texts.invoice')])
    @include('portal.ninja2020.invoices.includes.signature')
@endsection

@section('footer')
    <script src="{{ asset('js/clients/shared/pdf.js') }}"></script>
    <script src="{{ asset('js/clients/invoices/payment.js') }}"></script>
@endsection
