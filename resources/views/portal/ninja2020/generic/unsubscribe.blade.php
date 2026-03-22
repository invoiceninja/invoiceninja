@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.unsubscribed'))

@section('body')

    <div class="flex h-screen">
        <div class="m-auto md:w-1/3 lg:w-1/5">
            <div class="flex flex-col items-center">
                <img src="{{ $logo }}" class="border-b border-gray-100 h-18 pb-4" alt="Invoice Ninja logo">
                <h1 class="text-center text-3xl mt-10">{{ ctrans('texts.unsubscribed') }}</h1>
                <p class="text-center opacity-75">{{ ctrans('texts.unsubscribed_text') }}</p>
            </div>
        </div>
    </div>

@stop

@push('footer')

@endpush
