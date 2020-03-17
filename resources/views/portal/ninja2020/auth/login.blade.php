@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.login'))

@section('body')
    <div class="grid lg:grid-cols-3">
        <div class="hidden lg:block col-span-1 bg-red-100 h-screen">
            <img src="https://www.invoiceninja.com/wp-content/uploads/2018/04/bg-home2018b.jpg"
                 class="w-full h-screen object-cover"
                 alt="Background image">
        </div>
        <div class="col-span-2 h-screen flex">
            <div class="m-auto md:w-1/2 lg:w-1/4">
                <div class="flex flex-col">
                    <h1 class="text-center text-3xl">{{ ctrans('texts.account_login') }}</h1>
                    <p class="text-center mt-1 text-gray-600">{{ ctrans('texts.account_login_text') }}</p>
                    <form action="{{ route('client.login') }}" method="post" class="mt-6">
                        @csrf
                        <div class="flex flex-col">
                            <label for="email" class="input-label">{{ ctrans('texts.email_address') }}</label>
                            <input type="email" name="email" id="email"
                                   class="input"
                                   value="{{ old('email') }}"
                                   autofocus>
                            @error('email')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="flex flex-col mt-4">
                            <div class="flex justify-between items-center">
                                <label for="password" class="input-label">{{ ctrans('texts.password') }}</label>
                                <a class="text-xs text-gray-600 hover:text-gray-800 ease-in duration-100"
                                   href="{{ route('client.password.request') }}">{{ trans('texts.forgot_password') }}</a>
                            </div>
                            <input type="password" name="password" id="password"
                                   class="input"
                                   autofocus>
                            @error('password')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="mt-5">
                            <button class="button button-primary button-block">
                                {{ trans('texts.login') }}
                            </button>
                        </div>
                    </form>
                    <a href="#" class="uppercase text-sm mt-4 text-center text-grey-600 hover:text-blue-600 ease-in duration-100">
                        {{ trans('texts.login_create_an_account') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
