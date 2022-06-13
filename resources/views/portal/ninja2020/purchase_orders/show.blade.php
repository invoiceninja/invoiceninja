@extends('portal.ninja2020.layout.vendor_app')
@section('meta_title', ctrans('texts.view_purchase_order'))

@push('head')
    <meta name="require-invoice-signature" content="{{ $purchase_order->vendor->user->account->hasFeature(\App\Models\Account::FEATURE_INVOICE_SETTINGS) && $settings->require_purchase_order_signature }}">
    @include('portal.ninja2020.components.no-cache')
    
    <script src="{{ asset('vendor/signature_pad@2.3.2/signature_pad.min.js') }}"></script>

@endpush

@section('body')

    @if($purchase_order)
        <form action="{{ ($settings->client_portal_allow_under_payment || $settings->client_portal_allow_over_payment) ? route('client.invoices.bulk') : route('client.payments.process') }}" method="post" id="payment-form">
            @csrf
            <input type="hidden" name="signature">

            <div class="bg-white shadow sm:rounded-lg mb-4" translate>
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                {{ ctrans('texts.purchase_order_number_placeholder', ['purchase_order' => $purchase_order->number])}}
                                - {{ ctrans('texts.unpaid') }}
                            </h3>

                            @if($key)
                            <div class="btn hidden md:block" data-clipboard-text="{{url("vendor/purchase_order/{$key}")}}" aria-label="Copied!">
                                <div class="flex text-sm leading-6 font-medium text-gray-500">
                                    <p class="mr-2">{{url("vendor/purchase_order/{$key}")}}</p>
                                    <p><img class="h-5 w-5" src="{{ asset('assets/clippy.svg') }}" alt="Copy to clipboard"></p>
                                </div>
                            </div>
                            @endif


                        </div>
                        <div class="mt-5 sm:mt-0 sm:ml-6 flex justify-end">
                            <div class="inline-flex rounded-md shadow-sm">
                                <input type="hidden" name="purchase_orders[]" value="{{ $purchase_order->hashed_id }}">
                                <input type="hidden" name="action" value="payment">

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
                            {{ ctrans('texts.invoice_number_placeholder', ['invoice' => $purchase_order->number])}}
                            - {{ \App\Models\PurchaseOrder::stringStatus($purchase_order->status_id) }}
                        </h3>

                            @if($key)
                            <div class="btn hidden md:block" data-clipboard-text="{{url("client/invoice/{$key}")}}" aria-label="Copied!">
                                <div class="flex text-sm leading-6 font-medium text-gray-500">
                                    <p class="pr-10">{{url("client/invoice/{$key}")}}</p>
                                    <p><img class="h-5 w-5" src="{{ asset('assets/clippy.svg') }}" alt="Copy to clipboard"></p>
                                </div>
                            </div>
                            @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include('portal.ninja2020.components.entity-documents', ['entity' => $purchase_order])
    @include('portal.ninja2020.components.pdf-viewer', ['entity' => $purchase_order])
    @include('portal.ninja2020.invoices.includes.terms', ['entities' => [$purchase_order], 'entity_type' => ctrans('texts.purchase_order')])
    @include('portal.ninja2020.invoices.includes.signature')
@endsection

@section('footer')
    <script src="{{ asset('js/clients/invoices/payment.js') }}"></script>
    <script src="{{ asset('vendor/clipboard.min.js') }}"></script>

    <script type="text/javascript">

        var clipboard = new ClipboardJS('.btn');

    </script>
@endsection
