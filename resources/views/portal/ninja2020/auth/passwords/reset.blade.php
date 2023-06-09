@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.password_recovery'))

@section('body')
    <div class="grid lg:grid-cols-3">
        @if($account && !$account->isPaid())
        <div class="hidden lg:block col-span-1 bg-red-100 h-screen">
            <img src="{{ asset('images/client-portal-new-image.jpg') }}"
                 class="w-full h-screen object-cover"
                 alt="Background image">
        </div>
        @endif
      
        <div class="{{ $account && !$account->isPaid() ? 'col-span-2' : 'col-span-3' }} h-screen flex">
            <div class="m-auto w-1/2 md:w-1/3 lg:w-1/4">
                @if($account && !$account->isPaid())
                    <div>
                        <img src="{{ asset('images/invoiceninja-black-logo-2.png') }}" class="border-b border-gray-100 h-18 pb-4" alt="Invoice Ninja logo">
                    </div>
                @elseif(isset($company) && !is_null($company))
                    <div>
                        <img src="{{ asset($company->present()->logo()) }}" class="h-14 mb-10" alt="{{ $company->present()->name() }} logo">
                    </div>
                @endif
                <div class="flex flex-col">
                    <h1 class="text-center text-3xl">{{ ctrans('texts.password_recovery') }}</h1>
                    <p class="text-center mt-1 text-gray-600">{{ ctrans('texts.reset_password_text') }}</p>
                    @if(session('status'))
                        <div class="alert alert-success mt-4">
                            {{ session('status') }}
                        </div>
                    @endif
                    <form action="{{ route('client.password.update') }}" method="post" class="mt-6">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        @if($company)
                            <input type="hidden" name="company_key" value="{{$company->company_key}}">
                        @endif
                        <div class="flex flex-col">
                            <label for="email" class="input-label">{{ ctrans('texts.email_address') }}</label>
                            <input type="email" name="email" id="email"
                                   class="input"
                                   value="{{ $email ?? old('email') }}"
                                   >
                            @error('email')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="flex flex-col mt-4">
                            <label for="password" class="input-label">{{ ctrans('texts.password') }}</label>
                            <input type="password" name="password" id="password"
                                   class="input"
                                   autofocus>
                            @error('password')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="flex flex-col mt-4">
                            <label for="password" class="input-label">{{ ctrans('texts.password') }}</label>
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                   class="input"
                                   >
                            @error('password_confirmation')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="mt-5">
                            <button class="button button-primary button-block bg-blue-600">{{ ctrans('texts.complete') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
