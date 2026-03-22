@extends('portal.ninja2020.layout.clean')
@section('meta_title', $__env->yieldContent('title'))

@section('body')
    <div class="grid lg:grid-cols-3 mx-6 md:mx-0">
        @if(isset($account) && !$account->isPaid())
            <div class="hidden lg:block col-span-1 bg-red-100 h-screen">
                <img src="{{ asset('images/client-portal-new-image.jpg') }}"
                     class="w-full h-screen object-cover"
                     alt="Background image">
            </div>
        @endif

        <div class="{{ isset($account) && !$account->isPaid() ? 'col-span-2' : 'col-span-3' }} h-screen flex">
            <div class="m-auto flex-col items-center">
                @if(isset($account) && !$account->isPaid())
                    <div>
                        <img src="{{ asset('images/invoiceninja-black-logo-2.png') }}"
                             class="border-b border-gray-100 h-18 pb-4" alt="Invoice Ninja logo">
                    </div>
                @elseif(isset($company) && !is_null($company))
                    <div>
                        <img src="{{ $company->present()->logo()  }}"
                             class="mx-auto border-b border-gray-100 h-18 pb-4" alt="{{ $company->present()->name() }} logo">
                    </div>
                @endif
            <div class="m-auto flex-col items-center mt-4">
                <span class="flex items-center text-2xl mt-4">
                    @yield('code') — @yield('message')
                </span>

                <a class="button-link text-sm mt-4" href="{{ url(request()->getSchemeAndHttpHost() . '/client') }}">
                    {{ ctrans('texts.back_to', ['url' => parse_url(request()->getHttpHost())['host'] ?? request()->getHttpHost()]) }}
                </a>
            </div>
        </div>
    </div>
@endsection


