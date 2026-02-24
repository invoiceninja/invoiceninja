@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.register'))

@section('body')
@php
    $logo = optional($company?->present())->logo() ?? asset('images/invoiceninja-black-logo-2.png');
    $logoAlt = optional($company?->present())->name() ?? 'Company logo';
    $commonClasses = "w-full text-sm sm:text-base border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500";
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
                ? 'w-full sm:w-2/3 md:w-1/2 lg:w-1/2 xl:w-1/2' 
                : 'w-full sm:w-2/3 md:w-1/2 lg:w-1/3 xl:w-1/3' 
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

                <h1 class="text-3xl font-bold text-gray-800">{{ ctrans('texts.register') }}</h1>
                <p class="text-gray-600 text-sm sm:text-base mt-1">{{ ctrans('texts.register_label') }}</p>
            </div>

            <form id="registerForm" action="{{ route('client.register', request()->route('company_key')) }}" method="POST" class="space-y-5">
                @csrf
                @if($company)
                    <input type="hidden" name="company_key" value="{{ $company->company_key }}">
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                @foreach($company->client_registration_fields as $field)
                    @if(isset($field['visible']) && $field['visible'])

                    @php
                        $leftFields = ['first_name','email','password'];
                        $rightFields = ['last_name','phone','password_confirmation'];
                        $columnClass = in_array($field['key'],$rightFields) ? 'md:col-start-2' : 'md:col-start-1';
                    @endphp

                    <div class="flex flex-col {{ $columnClass }}">
                        <label for="{{ $field['key'] }}" class="text-sm sm:text-base font-bold text-gray-700 mb-1">
                            @if(in_array($field['key'], ['custom_value1','custom_value2','custom_value3','custom_value4']))
                                {{ (new App\Utils\Helpers())->makeCustomField($company->custom_fields, str_replace("custom_value","client",$field['key'])) }}
                            @else
                                {{ ctrans("texts.{$field['key']}") }}
                            @endif
                            @if($field['required'])<span class="text-red-400 ml-1 text-sm">*</span>@endif
                        </label>

                        @php $isRequired = $field['required'] ? 'required' : ''; @endphp

                        @if($field['key'] === 'email')
                            <input id="{{ $field['key'] }}" type="email" name="{{ $field['key'] }}" value="{{ old($field['key']) }}" {{ $isRequired }} class="{{ $commonClasses }}">
                        @elseif($field['key'] === 'password')
                            <div class="relative w-full">
                                <input id="password" type="password" name="password" {{ $isRequired }} class="{{ $commonClasses }} pr-10">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 flex items-center justify-center w-10 text-gray-400 hover:text-gray-600 focus:outline-none">
                                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 5C7.523 5 3.732 7.943 2.458 12c1.274 4.057 5.065 7 9.542 7s8.268-2.943 9.542-7C20.268 7.943 16.477 5 12 5z" fill="{{ $eyeColor }}"/>
                                        <circle cx="12" cy="12" r="3" fill="{{ $eyeColor }}" stroke="white" stroke-width="1.5"/>
                                    </svg>
                                </button>
                            </div>
                        @elseif($field['key'] === 'currency_id' || $field['key'] === 'country_id')
                            <select name="{{ $field['key'] }}" {{ $isRequired }} class="{{ $commonClasses }} form-select">
                                @if($field['key'] === 'country_id')
                                    <option value=""></option>
                                    @foreach(App\Utils\TranslationHelper::getCountries() as $country)
                                        <option value="{{ $country->id }}">{{ $country->iso_3166_2 }} ({{ $country->getName() }})</option>
                                    @endforeach
                                @else
                                    @foreach(App\Utils\TranslationHelper::getCurrencies() as $currency)
                                        <option value="{{ $currency->id }}" {{ $currency->id == $company->settings->currency_id ? 'selected' : '' }}>{{ $currency->getName() }}</option>
                                    @endforeach
                                @endif
                            </select>
                        @else
                            <input name="{{ $field['key'] }}" value="{{ old($field['key']) }}" {{ $isRequired }} class="{{ $commonClasses }}">
                        @endif

                        @error($field['key'])
                            <div class="text-red-600 text-xs sm:text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    @endif
                @endforeach

                <div class="flex flex-col md:col-start-2">
                    <label for="password_confirmation" class="text-sm sm:text-base font-bold text-gray-700 mb-1">
                        {{ ctrans('texts.confirm_password') }}<span class="text-red-400 ml-1 text-sm">*</span>
                    </label>
                    <div class="relative w-full">
                        <input id="password_confirmation" type="password" name="password_confirmation" required class="{{ $commonClasses }} pr-10">
                        <button type="button" id="togglePasswordConfirm" class="absolute inset-y-0 right-0 flex items-center justify-center w-10 text-gray-400 hover:text-gray-600 focus:outline-none">
                            <svg id="eyeIconConfirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 5C7.523 5 3.732 7.943 2.458 12c1.274 4.057 5.065 7 9.542 7s8.268-2.943 9.542-7C20.268 7.943 16.477 5 12 5z" fill="{{ $eyeColor }}"/>
                                <circle cx="12" cy="12" r="3" fill="{{ $eyeColor }}" stroke="white" stroke-width="1.5"/>
                            </svg>
                        </button>
                    </div>
                    <span id="confirmMsg" class="text-red-500 text-sm mt-1"></span>
                </div>

                </div>

                <div class="w-full md:w-1/2 mx-auto">
                    <button type="submit" class="w-full py-3 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                        {{ ctrans('texts.register') }}
                    </button>
                </div>

                <div class="text-center mt-3">
                    <a href="{{ route('client.login') }}" class="text-blue-600 font-bold hover:underline text-sm sm:text-base">
                        {{ ctrans('texts.login') }}
                    </a>
                </div>
            </form>

            @if(!is_null($company) && !empty($company->present()->website()))
                @php $host = parse_url($company->present()->website(), PHP_URL_HOST) ?? $company->present()->website(); @endphp
                <div class="mt-4 text-center">
                    <a href="{{ $company->present()->website() }}" class="text-gray-500 hover:text-gray-700 text-sm sm:text-base">
                        {{ ctrans('texts.back_to', ['url' => $host]) }}
                    </a>
                </div>
            @endif

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const eyeColor='{{ $eyeColor }}';
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirmation');
    const togglePassword = document.getElementById('togglePassword');
    const togglePasswordConfirm = document.getElementById('togglePasswordConfirm');
    const eyeIcon = document.getElementById('eyeIcon');
    const eyeIconConfirm = document.getElementById('eyeIconConfirm');
    const confirmMsg = document.getElementById('confirmMsg');

    function toggle(btn, input, icon){
        btn.addEventListener('click', ()=>{
            const type = input.type==='password' ? 'text' : 'password';
            input.type = type;
            icon.innerHTML = type==='text'
                ? `<path d="M12 5C7.523 5 3.732 7.943 2.458 12c1.274 4.057 5.065 7 9.542 7s8.268-2.943 9.542-7C20.268 7.943 16.477 5 12 5z" fill="${eyeColor}"/>
                   <circle cx="12" cy="12" r="3" fill="${eyeColor}" stroke="white" stroke-width="1.5"/>
                   <line x1="5" y1="20" x2="19" y2="4" stroke="${eyeColor}" stroke-width="2"/>`
                : `<path d="M12 5C7.523 5 3.732 7.943 2.458 12c1.274 4.057 5.065 7 9.542 7s8.268-2.943 9.542-7C20.268 7.943 16.477 5 12 5z" fill="${eyeColor}"/>
                   <circle cx="12" cy="12" r="3" fill="${eyeColor}" stroke="white" stroke-width="1.5"/>`;
        });
    }

    toggle(togglePassword, passwordInput, eyeIcon);
    toggle(togglePasswordConfirm, confirmInput, eyeIconConfirm);

    function checkMismatch() {
        if(confirmInput.value !== '' && passwordInput.value !== confirmInput.value){
            confirmMsg.textContent = 'Passwords do not match';
        } else {
            confirmMsg.textContent = '';
        }
    }

    passwordInput.addEventListener('input', checkMismatch);
    confirmInput.addEventListener('input', checkMismatch);
});
</script>
@endsection
