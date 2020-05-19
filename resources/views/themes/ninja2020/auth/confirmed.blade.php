@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.confirmation'))

@section('body')
    <div class="flex h-screen">
        <div class="m-auto md:w-1/3 lg:w-1/5">
            <div class="flex flex-col items-center">
                <h1 class="text-center text-3xl">{{ ctrans('texts.confirmation') }}</h1>
                <p class="text-center opacity-75">{{ $message }}</p>
                <a class="button button-primary text-center mt-8" href="{{ url('/') }}">{{ ctrans('texts.return_to_login') }}</a>
            </div>
        </div>
    </div>
@endsection