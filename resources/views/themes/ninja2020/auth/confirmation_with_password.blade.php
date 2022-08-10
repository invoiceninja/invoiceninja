@extends('themes.ninja2020.clean')
@section('meta_title', ctrans('texts.set_password'))

@section('body')
    <div class="flex h-screen">
        <div class="m-auto md:w-1/3 lg:w-1/5">
            <div class="flex flex-col">
                <img src="{{ asset('images/invoiceninja-black-logo-2.png') }}" class="border-b border-gray-100 h-18 pb-4" alt="Invoice Ninja logo">
                <h1 class="text-center text-3xl mt-10">{{ ctrans('texts.set_password') }}</h1>
                <span class="text-gray-900 text-sm text-center">{{ ctrans('texts.update_password_on_confirm') }}</span>

                <form action="{{ url()->current() }}" method="post" class="mt-6">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $user_id }}">
                    <div class="flex flex-col mt-4">
                        <label for="password" class="input-label">{{ ctrans('texts.password') }}</label>
                        <input type="password" name="password" id="password"
                               class="input"
                               autofocus required>
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
                               autofocus required>
                        @error('password_confirmation')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>
                    <div class="mt-5">
                        <button class="button button-primary button-block bg-blue-600">{{ ctrans('texts.update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
