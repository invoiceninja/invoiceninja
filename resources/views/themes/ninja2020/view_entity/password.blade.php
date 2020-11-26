@extends('portal.ninja2020.layout.clean')

@section('body')
    <div class="flex h-screen">
        <div class="m-auto md:w-1/3 lg:w-1/5">
            <div class="flex flex-col">
                <h1 class="text-center text-3xl">{{ ctrans('texts.password') }}</h1>
                <p class="text-sm text-center text-gray-700">{{ ctrans('texts.to_view_entity_password', ['entity' => $entity_type]) }}</p>
                <form method="post" class="mt-6">
                    @csrf
                    <div class="flex flex-col">
                        <div class="flex justify-between items-center">
                            <label for="password" class="input-label">{{ ctrans('texts.password') }}</label>
                            <a class="text-xs text-gray-600 hover:text-gray-800 ease-in duration-100" href="{{ route('client.password.request') }}">{{ trans('texts.forgot_password') }}</a>
                        </div>
                        <input type="password" name="password" id="password" class="input" autofocus>

                        @if(session('PASSWORD_FAILED'))
                            <div class="validation validation-fail">
                                {{ ctrans('auth.failed') }}
                            </div>
                        @endif
                    </div>
                    <div class="mt-5">
                        <button class="button button-primary button-block bg-blue-600">{{ ctrans('texts.continue') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection