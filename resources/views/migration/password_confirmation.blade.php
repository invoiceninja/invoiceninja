@extends('migration.layout.main', [
    'step_title' => 'Please verify your password',
    'step_description' => '.. just to make sure.'
])

@section('body')
    <div class="flex justify-center my-5">
        <div class="w-1/2">

            @include('migration.includes.message')

            <form action="/migration/password_confirmation" method="post">

                {{ csrf_field() }}

                <div class="flex flex-col mt-5">
                    <label for="password"
                           class="text-sm uppercase text-gray-900 mb-1">{!! trans('texts.password') !!}</label>
                    <input type="password" name="password"
                           class="p-2 bg-white rounded shadow focus:shadow-lg focus:outline-none">
                </div>

                <button type="submit"
                        class="bg-blue-700 hover:shadow hover:bg-blue-800 w-full rounded mt-5 text-white py-2">
                    Verify
                </button>

            </form>

        </div>
    </div>
@stop
