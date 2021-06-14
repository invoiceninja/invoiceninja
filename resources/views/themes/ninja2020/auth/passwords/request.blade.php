@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.password_recovery'))

@section('body')
    <div class="flex h-screen">

        <div class="m-auto md:w-1/3 lg:w-1/5">
            
        @if($account && !$account->isPaid())
        <div>
            <img src="{{ asset('images/invoiceninja-black-logo-2.png') }}" class="border-b border-gray-100 h-18 pb-4" alt="Invoice Ninja logo">
        </div>
        @endif

            <div class="flex flex-col">
                <h1 class="text-center text-3xl">{{ ctrans('texts.password_recovery') }}</h1>
                <p class="text-center opacity-75">{{ ctrans('texts.reset_password_text') }}</p>
                @if(session('status'))
                    <div class="alert alert-success mt-4">
                        {{ session('status') }}
                    </div>
                @endif
                <form action="{{ route('password.email') }}" method="post" class="mt-6">
                    @csrf
                    <div class="flex flex-col">

                        <label for="email" class="text-sm text-gray-600">{{ ctrans('texts.email_address') }}</label>
                        <input type="email" name="email" id="email"
                               class="input"
                               placeholder="user@example.com"
                               value="{{ old('email') }}"
                               autofocus>
                        @error('email')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>
                    <div class="mt-5">
                        <button class="button button-primary button-block bg-blue-600">{{ ctrans('texts.next_step') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
