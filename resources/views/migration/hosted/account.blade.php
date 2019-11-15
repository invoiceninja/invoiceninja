@extends('migration.layout.main', [
    'step_title' => 'Authentication',
    'step_description' => 'To continue, please login with your account.'
])

@section('body')
    <div class="flex justify-center my-5">
        <div class="w-1/2">

            @if($errors->any())
                <div class="mb-5 bg-red-800 p-4 rounded">
                    @foreach($errors->all() as $error)
                        <p class="text-white block">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form action="/migration/self_hosted/credentials" method="post">
                {!! csrf_field() !!}
                <input name="_method" value="put" hidden>

                <div class="flex flex-col">
                    <label for="email_address">{!! trans('texts.email_address') !!}</label>
                    <input type="email" name="email_address"
                           class="p-2 bg-gray-200 rounded focus:bg-gray-300 focus:outline-none"
                           value="{{ old('email_password') }}">
                </div>

                <div class="flex flex-col mt-5">
                    <label for="password">{!! trans('texts.password') !!}</label>
                    <input type="password" name="password"
                           class="p-2 bg-gray-200 rounded focus:bg-gray-300 focus:outline-none">
                </div>

                <div class="flex flex-col mt-5">
                    <label for="x_api_secret">X-API-SECRET</label>
                    <input type="text" name="x_api_secret"
                           class="p-2 bg-gray-200 rounded focus:bg-gray-300 focus:outline-none">
                </div>

                <div class="flex flex-col mt-5">
                    <label for="api_endpoint">Self-hosted URL (with http://):</label>
                    <input type="text" name="api_endpoint"
                           class="p-2 bg-gray-200 rounded focus:bg-gray-300 focus:outline-none">
                </div>

                <button type="submit" class="bg-blue-700 hover:bg-blue-800 w-full rounded mt-3 text-white py-2">Log in
                </button>

            </form>
        </div>
    </div>
@stop
