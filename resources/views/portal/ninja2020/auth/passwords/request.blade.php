@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.password_recovery'))

@section('body')
@php
    $logoSrc = $account && !$account->isPaid()
        ? asset('images/invoiceninja-black-logo-2.png')
        : optional($company->present())->logo();

    $logoAlt = $account && !$account->isPaid()
        ? 'Invoice Ninja logo'
        : (optional($company->present())->name() ?? 'Company logo');
@endphp


<div class="grid lg:grid-cols-3 mx-6 md:mx-0 min-h-screen">

    @if($account && !$account->isPaid())
        <div class="hidden lg:block col-span-1">
            <img src="{{ asset('images/client-portal-new-image.jpg') }}"
                 class="w-full h-screen object-cover"
                 alt="Background image">
        </div>
    @endif


    <div class="{{ $account && !$account->isPaid() ? 'col-span-2' : 'col-span-3' }} flex items-center justify-center">

        <div class="{{ $account && !$account->isPaid() 
                    ? 'w-full sm:w-2/3 md:w-1/3 lg:w-1/3 xl:w-1/3' 
                    : 'w-full sm:w-2/3 md:w-1/3 lg:w-1/4 xl:w-1/4' 
                }} bg-white rounded-2xl p-8 sm:p-10 shadow-lg"
             style="box-shadow: 0 0 20px rgba(0,0,0,0.15);">

            @if($logoSrc)
                <div class="text-center mb-6 sm:mb-8 border-b border-gray-200 pb-4">
                    <img src="{{ $logoSrc }}"
                         class="h-16 sm:h-20 mx-auto mb-4"
                         alt="{{ $logoAlt }}">

                    <h1 class="text-3xl font-bold text-gray-800">
                        {{ ctrans('texts.password_recovery') }}
                    </h1>

                    <p class="text-gray-600 text-sm sm:text-base mt-1">
                        {{ ctrans('texts.reset_password_text') }}
                    </p>
                </div>
            @endif


            @if(session('status'))
                <div class="mb-4 text-center px-4 py-3 rounded-lg bg-green-100 text-green-800 border-l-4 border-green-500">
                    {{ session('status') }}
                </div>
            @endif


            <form id="passwordRecoveryForm"
                  action="{{ route($passwordEmailRoute) }}"
                  method="post"
                  class="space-y-5">

                @csrf

                @if($company)
                    <input type="hidden" name="company_key" value="{{ $company->company_key }}">
                @endif


                <div class="flex flex-col">
                    <label for="email"
                           class="text-sm sm:text-base font-bold text-gray-700">
                        {{ ctrans('texts.email_address') }}
                    </label>

                    <input type="email"
                           name="email"
                           id="email"
                           value="{{ old('email') }}"
                           required autofocus
                           class="mt-2 px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white text-sm sm:text-base">

                    @error('email')
                        <span class="text-red-500 text-sm mt-1">
                            {{ $message }}
                        </span>
                    @enderror
                </div>


                <button type="submit"
                        class="w-full py-3 mt-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                    {{ ctrans('texts.send_email') }}
                </button>


                <div class="text-center mt-3">
                    <a href="{{ route('client.login') }}"
                       class="text-blue-600 font-bold hover:underline text-sm sm:text-base">
                        {{ ctrans('texts.login') }}
                    </a>
                </div>
            </form>


            @if(!is_null($company) && !empty($company->present()->website()))
                @php
                    $host = parse_url($company->present()->website(), PHP_URL_HOST)
                        ?? $company->present()->website();
                @endphp

                <div class="mt-4 text-center">
                    <a href="{{ $company->present()->website() }}"
                       class="text-gray-500 hover:text-gray-700 text-sm sm:text-base">
                        {{ ctrans('texts.back_to', ['url' => $host]) }}
                    </a>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection
