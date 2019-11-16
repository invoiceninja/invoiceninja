@extends('migration.layout.main', [
    'step_title' => 'Authentication',
    'step_description' => 'To continue, please login with your account.'
])

@section('body')
    <div class="flex justify-center my-5">
        <div class="w-1/2">

            @include('migration.includes.message')

            <form action="/migration/self_hosted/credentials" method="post">
                {{ csrf_field() }}
                <input hidden name="_method" value="put">

                <div class="flex flex-col">
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

                <button type="submit"
                        class="bg-blue-700 hover:shadow hover:bg-blue-800 w-full rounded mt-5 text-white py-2">
                    Check connection
                </button>

            </form>
        </div>
    </div>
@stop
