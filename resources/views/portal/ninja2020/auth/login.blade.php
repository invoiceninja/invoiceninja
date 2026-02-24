@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.login'))

@section('body')
    @php
        $logo = optional($company?->present())->logo() ?? asset('images/invoiceninja-black-logo-2.png');
        $logoAlt = optional($company?->present())->name() ?? 'Company logo';
        $eyeColor = 'rgb(156 163 175 / var(--tw-text-opacity, 1))';
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

                <div class="text-center mb-6 sm:mb-8 border-b border-gray-200 pb-4">

                    @if($account && !$account->isPaid())
                        <img src="{{ asset('images/invoiceninja-black-logo-2.png') }}"
                             class="h-16 sm:h-20 mx-auto mb-4"
                             alt="Invoice Ninja logo"
                             id="company_logo">
                    @else
                        <img src="{{ $logo }}" alt="{{ $logoAlt }}" class="h-16 sm:h-20 mx-auto mb-4">
                    @endif

                    <h1 class="text-3xl font-bold text-gray-800">{{ ctrans('texts.client_portal') }}</h1>
                </div>

                <form id="loginForm" action="{{ route('client.login') }}" method="post" class="space-y-5">
                    @csrf

                    <div class="flex flex-col">
                        <label for="email" class="text-sm sm:text-base font-bold text-gray-700">{{ ctrans('texts.email_address') }}</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                               class="mt-2 px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white text-sm sm:text-base">
                        @error('email')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex flex-col mt-4">
                        <label for="password" class="text-sm sm:text-base font-bold text-gray-700">{{ ctrans('texts.password') }}</label>

                        <div class="relative mt-2">
                            <input type="password" name="password" id="password" required autocomplete="current-password"
                                   class="w-full px-4 pr-10 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white text-sm sm:text-base">

                            <button type="button" id="togglePassword"
                                    class="absolute inset-y-0 right-0 flex items-center justify-center w-10 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg"
                                     class="h-5 w-5 pointer-events-none"
                                     viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 5C7.523 5 3.732 7.943 2.458 12c1.274 4.057 5.065 7 9.542 7s8.268-2.943 9.542-7C20.268 7.943 16.477 5 12 5z" fill="{{ $eyeColor }}"/>
                                    <circle cx="12" cy="12" r="3" fill="{{ $eyeColor }}" stroke="white" stroke-width="1.5"/>
                                </svg>
                            </button>
                        </div>

                        @error('password')
                            <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    @if(isset($company) && !is_null($company))
                        <input type="hidden" name="company_key" value="{{ $company->company_key }}">
                    @endif

                    <button type="submit"
                            class="w-full py-3 mt-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                        {{ trans('texts.login') }}
                    </button>

                    <div class="text-center mt-3">
                        <div>
                            <a href="{{ route('client.password.request') }}"
                               class="text-blue-600 font-bold hover:underline text-sm sm:text-base">
                                {{ trans('texts.forgot_password') }}
                            </a>
                        </div>

                        @if(!is_null($company) && $company->client_can_register)
                            <div class="mt-1">
                                <a href="{{ route('client.register') }}"
                                   class="text-blue-600 font-bold hover:underline text-sm sm:text-base">
                                    {{ ctrans('texts.register') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </form>

                @if(!is_null($company) && !empty($company->present()->website()))
                    @php $host = parse_url($company->present()->website(), PHP_URL_HOST) ?? $company->present()->website(); @endphp
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeColor = '{{ $eyeColor }}';

            togglePassword.addEventListener('click', function() {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                eyeIcon.innerHTML = type === 'text'
                    ? `<path d="M12 5C7.523 5 3.732 7.943 2.458 12c1.274 4.057 5.065 7 9.542 7s8.268-2.943 9.542-7C20.268 7.943 16.477 5 12 5z" fill="${eyeColor}"/>
                       <circle cx="12" cy="12" r="3" fill="${eyeColor}" stroke="white" stroke-width="1.5"/>
                       <line x1="5" y1="20" x2="19" y2="4" stroke="${eyeColor}" stroke-width="2"/>`
                    : `<path d="M12 5C7.523 5 3.732 7.943 2.458 12c1.274 4.057 5.065 7 9.542 7s8.268-2.943 9.542-7C20.268 7.943 16.477 5 12 5z" fill="${eyeColor}"/>
                       <circle cx="12" cy="12" r="3" fill="${eyeColor}" stroke="white" stroke-width="1.5"/>`;
            });
        });
    </script>
@endsection
