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
    <input type="hidden" name="company_gateway_id" id="company_gateway_id">
    <input type="hidden" name="payment_method_id" id="payment_method_id">
    <input type="hidden" name="signature">

    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-6 md:col-start-2 md:col-span-4">
                <div class="flex justify-end">
                    <div class="flex justify-end mb-2">
                        <!-- Pay now button -->
                        @if(count($payment_methods) > 0)
                        <div x-data="{ open: false }" @keydown.window.escape="open = false" @click.away="open = false" class="relative inline-block text-left" data-cy="payment-methods-dropdown">
                            <div>
                                <div class="rounded-md shadow-sm">
                                    <button @click="open = !open" type="button" class="inline-flex justify-center w-full px-4 py-2 text-sm font-medium leading-5 text-gray-700 transition duration-150 ease-in-out bg-white border border-gray-300 rounded-md hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue active:bg-gray-50 active:text-gray-800">
                                        {{ ctrans('texts.pay_now') }}
                                        <svg class="w-5 h-5 ml-2 -mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div x-show="open" class="absolute right-0 w-56 mt-2 origin-top-right rounded-md shadow-lg">
                                <div class="bg-white rounded-md shadow-xs">
                                    <div class="py-1">
                                        @foreach($payment_methods as $payment_method)
                                            @if($payment_method['label'] == 'Custom')
                                                <a href="#" @click="{ open = false }" data-company-gateway-id="{{ $payment_method['company_gateway_id'] }}" data-gateway-type-id="{{ $payment_method['gateway_type_id'] }}" class="block px-4 py-2 text-sm leading-5 text-gray-700 dropdown-gateway-button hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:bg-gray-100 focus:text-gray-900" data-cy="payment-method">
                                                    {{ \App\Models\CompanyGateway::find($payment_method['company_gateway_id'])->firstOrFail()->getConfigField('name') }}
                                                </a>
                                            @else
                                                <a href="#" @click="{ open = false }" data-company-gateway-id="{{ $payment_method['company_gateway_id'] }}" data-gateway-type-id="{{ $payment_method['gateway_type_id'] }}" class="block px-4 py-2 text-sm leading-5 text-gray-700 dropdown-gateway-button hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:bg-gray-100 focus:text-gray-900" data-cy="payment-method">
                                                    {{ $payment_method['label'] }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        @else
                        <span class="inline-flex items-center text-sm">
                            <span>{{ ctrans('texts.to_pay_invoices') }} &nbsp;</span>
                            <a class="button-link text-primary" href="{{ route('client.payment_methods.index') }}">{{ ctrans('texts.add_payment_method_first') }}.</a>
                        </span>
                        @endif
                    </div>
                </div>

                @foreach($invoices as $key => $invoice)
                <input type="hidden" name="payable_invoices[{{$key}}][invoice_id]" value="{{ $invoice->hashed_id }}">
                <div class="mb-4 overflow-hidden bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">
                            {{ ctrans('texts.invoice') }}
                            <a class="button-link text-primary" href="{{ route('client.invoice.show', $invoice->hashed_id) }}">
                                (#{{ $invoice->number }})
                            </a>
                        </h3>
                    </div>
                    <div>
                        <dl>
                            @if(!empty($invoice->number) && !is_null($invoice->number))
                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.invoice_number') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ $invoice->number }}
                                </dd>
                            </div>
                            @endif

                            @if(!empty($invoice->due_date) && !is_null($invoice->due_date))
                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.due_date') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ $invoice->due_date }}
                                </dd>
                            </div>
                            @endif

                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.additional_info') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    @if($invoice->po_number)
                                        {{ $invoice->po_number }}
                                    @elseif($invoice->public_notes)
                                        {{ $invoice->public_notes }}
                                    @else
                                        {{ $invoice->date}}
                                    @endif
                                </dd>
                            </div>

                            @if(!empty($invoice->amount) && !is_null($invoice->amount))
                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.amount') }}
                                </dt>
                                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2 flex flex-col">
                                    <!-- App\Utils\Number::formatMoney($invoice->amount, $invoice->client) -->
                                    <!-- Disabled input field don't send it's value with request. -->
                                    @if(!$settings->client_portal_allow_under_payment && !$settings->client_portal_allow_over_payment)
                                        <input 
                                            name="payable_invoices[{{$key}}][amount]" 
                                            value="{{ $invoice->partial > 0 ? $invoice->partial : $invoice->balance }}"
                                            class="mt-1 text-sm text-gray-800"
                                            readonly />
                                    @else
                                        <div class="flex items-center">
                                            <input 
                                                type="text" 
                                                class="input mt-0 mr-4 relative" 
                                                name="payable_invoices[{{$key}}][amount]" 
                                                value="{{ $invoice->partial > 0 ? $invoice->partial : $invoice->balance }}"/>
                                                <span class="mt-2">{{ $invoice->client->currency()->code }} ({{ $invoice->client->currency()->symbol }})</span>
                                        </div>
                                    @endif  

                                    @if($settings->client_portal_allow_under_payment)
                                        <span class="mt-1 text-sm text-gray-800">{{ ctrans('texts.minimum_payment') }}: {{ $settings->client_portal_under_payment_minimum }}</span>
                                    @endif
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</form>

@include('portal.ninja2020.invoices.includes.terms', ['entities' => $invoices, 'entity_type' => ctrans('texts.invoice')])
@include('portal.ninja2020.invoices.includes.signature')

@endsection

@push('footer')
    <script src="{{ asset('js/clients/invoices/payment.js') }}"></script>
@endpush