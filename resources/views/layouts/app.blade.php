
@extends('portal.ninja2020.layout.vendor_app')
@section('meta_title', ctrans('texts.view_purchase_order'))

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">


@push('head')
    <meta name="show-purchase_order-terms" content="false">
    <meta name="require-purchase_order-signature" content="{{ $purchase_order->company->account->hasFeature(\App\Models\Account::FEATURE_INVOICE_SETTINGS) && property_exists($settings, 'require_purchase_order_signature') && $settings->require_purchase_order_signature }}">
    @include('portal.ninja2020.components.no-cache')
    
    <script src="{{ asset('vendor/signature_pad@2.3.2/signature_pad.min.js') }}"></script>

@endpush


@section('body')
    @if(count($purchase_order)<1)
    <div>No purchase order found!</div>
    @else
    @if($purchase_order->company->getSetting('vendor_portal_enable_uploads'))
        @component('portal.ninja2020.purchase_orders.includes.upload', ['purchase_order' => $purchase_order]) @endcomponent
    @endif

    @if(in_array($purchase_order->status_id, [\App\Models\PurchaseOrder::STATUS_SENT, \App\Models\PurchaseOrder::STATUS_DRAFT]))
    <div class="mb-4">
        @include('portal.ninja2020.purchase_orders.includes.actions', ['purchase_order' => $purchase_order])
    </div>
    @else
        <input type="hidden" id="approve-button">
        <div class="bg-white shadow sm:rounded-lg mb-4">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.purchase_order_number_placeholder', ['purchase_order' => $purchase_order->number])}}
                            - {{ \App\Models\PurchaseOrder::stringStatus($purchase_order->status_id) }}
                        </h3>

                            @if($key)
                            <div class="btn hidden md:block" data-clipboard-text="{{url("vendor/purchase_order/{$key}")}}" aria-label="Copied!">
                                <div class="flex text-sm leading-6 font-medium text-gray-500">
                                    <p class="pr-10">{{url("vendor/purchase_order/{$key}")}}</p>
                                    <p><img class="h-5 w-5" src="{{ asset('assets/clippy.svg') }}" alt="Copy to clipboard"></p>
                                </div>
                            </div>
                            @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}"></script>

    <!-- Fonts -->
    {{-- <link rel="dns-prefetch" href="https://fonts.gstatic.com"> --}}
    {{-- <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet" type="text/css" defer> --}}
    <style>
            @font-face {
              font-family: 'Open Sans';
              font-style: normal;
              font-weight: 400;
              font-stretch: 100%;
              font-display: swap;
              src: url( {{asset('css/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2')}}) format('woff2');
              unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
            }
    </style>

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light navbar-laravel">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'Laravel') }}
                </a>
                @auth("user")
                    You're a user!
                @endauth


    @include('portal.ninja2020.components.entity-documents', ['entity' => $purchase_order])
    @livewire('pdf-slot', ['entity' => $purchase_order, 'invitation' => $invitation, 'db' => $invitation->company->db])
    @endif
@endsection

@section('footer')
    @include('portal.ninja2020.invoices.includes.terms', ['entities' => [$purchase_order], 'entity_type' => ctrans('texts.purchase_order')])
    @include('portal.ninja2020.invoices.includes.signature')
@endsection

@push('head')
    <script src="{{ asset('js/clients/purchase_orders/accept.js') }}" defer></script>
    <script src="{{ asset('vendor/clipboard.min.js') }}"  defer></script>

    <script type="text/javascript">

        document.addEventListener('DOMContentLoaded', () => {

            var clipboard = new ClipboardJS('.btn');

        });


    </script>
@endpush

        <main class="py-4">
            @yield('content')
        </main>
    </div>
    
    @if($errors->any())
    @foreach($errors->all() as $error)
        <script>
            iziToast.error({
                title: '{{ $error }}',
                position: 'topRight',
                message: '{{ $error }}',
            });
        </script>
    @endforeach
    @endif

    @if(session()->get('error'))
        <script>
            iziToast.error({
                title: '',
                position: 'topRight',
                message: '{{ session()->get('error') }}',
            });
        </script>
    @endif

    @if(session()->get('success'))
        <script>
            iziToast.success({
                title: '',
                position: 'topRight',
                message: '{{ session()->get('success') }}',
            });
        </script>
    @endif
</body>
</html>

