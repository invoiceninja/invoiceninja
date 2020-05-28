@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.register'))

@section('body')
    <div class="grid lg:grid-cols-3">
        <div class="hidden lg:block col-span-1 bg-red-100 h-screen">
            <img src="https://www.invoiceninja.com/wp-content/uploads/2018/04/bg-home2018b.jpg" class="w-full h-screen object-cover" alt="Background image">
        </div>
        <div class="col-span-2 h-screen flex">
            <div class="m-auto md:w-1/2 lg:w-1/4">
                <div class="flex flex-col">
                    <h1 class="text-center text-3xl">{{ ctrans('texts.register') }}</h1>
                    <p class="block text-center text-gray-600">{{ ctrans('texts.register_label') }}</p>
                    <form action="{{ route('client.register', request()->route('company_key')) }}" method="post" class="mt-6">
                        @csrf
                        <div class="flex flex-col">
                            <label for="email" class="input-label">{{ ctrans('texts.email_address') }}</label>
                            <input type="email" name="email" id="email" class="input" value="{{ old('email') }}" autofocus>
                            @error('email')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="flex flex-col mt-4">
                            <label for="password" class="input-label">{{ ctrans('texts.password') }}</label>
                            <input type="password" name="password" id="password" class="input">
                            @error('password')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="flex flex-col mt-4">
                            <label for="email" class="input-label">{{ ctrans('texts.password_confirmation') }}</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="input">
                            @error('password_confirmation')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="mt-5">
                            <button id="loginBtn" class="button button-primary button-block">
                                {{ trans('texts.register') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection