@extends('portal.ninja2020.layout.clean')
@section('meta_title', 'Mollie')

@section('body')
    <div class="grid lg:grid-cols-3">
        <div class="hidden lg:block col-span-1 bg-red-100 h-screen">
            <img src="{{ asset('images/client-portal-new-image.jpg') }}"
                 class="w-full h-screen object-cover"
                 alt="Background image">
        </div>

        <div class="col-span-2 h-screen flex">
            <div class="m-auto md:w-1/2 lg:w-1/4 flex flex-col items-center">
                <span class="flex items-center text-2xl">
                    {{ ctrans('texts.payment_error_code',['code' => 500]) }}
                 </span>

                <a class="button-link text-sm mt-2" href="{{ url(request()->getSchemeAndHttpHost() . '/client') }}">
                    {{ ctrans('texts.back_to', ['url' => parse_url(request()->getHttpHost())['host'] ?? request()->getHttpHost()]) }}
                </a>
            </div>
        </div>
    </div>
@endsection


