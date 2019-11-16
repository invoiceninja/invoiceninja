@extends('migration.layout.main', [
    'step_title' => 'Account',
    'step_description' => 'We need few things, to create your account.'
])

@section('body')
    <div class="flex justify-center my-5">
        <div class="w-1/2">

            @include('migration.includes.message')

            <form action="/migration/self_hosted/credentials/create" method="post">

                {{ csrf_field() }}

                <input hidden name="_method" value="put">
                <input hidden name="_type" value="self_hosted">

                <div class="flex flex-col">
                    <label for="first_name"
                           class="text-sm uppercase text-gray-900 mb-1">{!! trans('texts.first_name') !!}</label>
                    <input type="text" name="first_name"
                           class="p-2 bg-white rounded shadow focus:shadow-lg focus:outline-none"
                           value="{{ old('first_name') }}">
                </div>

                <div class="flex flex-col mt-5">
                    <label for="last_name"
                           class="text-sm uppercase text-gray-900 mb-1">{!! trans('texts.last_name') !!}</label>
                    <input type="text" name="last_name"
                           class="p-2 bg-white rounded shadow focus:shadow-lg focus:outline-none"
                           value="{{ old('last_name') }}">
                </div>

                <div class="flex flex-col mt-5">
                    <label for="email_address"
                           class="text-sm uppercase text-gray-900 mb-1">{!! trans('texts.email_address') !!}</label>
                    <input type="email" name="email_address"
                           class="p-2 bg-white rounded shadow focus:shadow-lg focus:outline-none"
                           value="{{ old('email_password') }}">
                </div>

                <div class="flex flex-col mt-5">
                    <label for="password"
                           class="text-sm uppercase text-gray-900 mb-1">{!! trans('texts.password') !!}</label>
                    <input type="password" name="password"
                           class="p-2 bg-white rounded shadow focus:shadow-lg focus:outline-none">
                </div>

                <div class="flex flex-col mt-5">
                    <label for="x_api_secret" class="text-sm uppercase text-gray-900 mb-1">X-API-SECRET</label>
                    <input type="text" name="x_api_secret"
                           class="p-2 bg-white rounded shadow focus:shadow-lg focus:outline-none">
                </div>

                <div class="flex flex-col mt-5">
                    <label for="api_endpoint" class="text-sm uppercase text-gray-900 mb-1">Self-hosted URL (with
                        http://):</label>
                    <input type="text" name="api_endpoint"
                           class="p-2 bg-white rounded shadow focus:shadow-lg focus:outline-none">
                </div>

                <div class="flex items-center mt-5">
                    <input type="checkbox" name="tos" checked required class="mr-2">
                    <label for="terms_of_service" class="text-sm text-gray-900">
                        Terms of service
                    </label>
                </div>

                <div class="flex items-center mt-5">
                    <input type="checkbox" name="privacy_policy" checked required class="mr-2">
                    <label for="terms_of_service" class="text-sm text-gray-900">
                        Privacy policy
                    </label>
                </div>

                <button type="submit"
                        class="bg-blue-700 hover:shadow hover:bg-blue-800 w-full rounded mt-5 text-white py-2">
                    Create account
                </button>

            </form>

            <div class="flex justify-center my-5">
                <a class="text-gray-900 hover:text-black" href="/migration/self_hosted/credentials">
                    I already have an account
                </a>
            </div>

        </div>
    </div>
@stop
