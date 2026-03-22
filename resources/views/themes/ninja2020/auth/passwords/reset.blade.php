@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.password_reset'))

@section('body')
    <div class="flex h-screen">
        <div class="m-auto md:w-1/3 lg:w-1/5">

        @if($account && !$account->isPaid())
        <div>
            <img src="{{ asset('images/invoiceninja-black-logo-2.png') }}" class="border-b border-gray-100 h-18 pb-4" alt="Invoice Ninja logo">
        </div>
        @endif

            <div class="flex flex-col">
                <h1 class="text-center text-3xl">{{ ctrans('texts.password_reset') }}</h1>
                @if(session('status'))
                    <div class="alert alert-success mt-2">
                        {{ session('status') }}
                    </div>
                @endif
                <form action="{{ route('password.update') }}" method="post" class="mt-6">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    @if(request()->has('react') && request()->react == 'true')
                        <input type="hidden" name="react" value="true">
                    @endif
                    <div class="flex flex-col">
                        <label for="email" class="input-label">{{ ctrans('texts.email_address') }}</label>
                        <input type="email" name="email" id="email"
                               class="input"
                               value="{{ $email ?? old('email') }}"
                               autofocus>
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
                        <label for="password" class="input-label">{{ ctrans('texts.password_confirmation') }}</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               class="input"
                               autofocus>
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
@endsection
