@extends('portal.ninja2020.layout.clean')
@section('meta_title', $__env->yieldContent('title'))

@section('body')
    <div class="grid lg:grid-cols-3">
        <div class="hidden lg:block col-span-1 bg-red-100 h-screen">
            <img src="{{ asset('images/client-portal-new-image.jpg') }}"
                 class="w-full h-screen object-cover"
                 alt="Background image">
        </div>

        <div class="col-span-2 h-screen flex">
            <div class="m-auto md:w-1/2 lg:w-1/3 flex flex-col items-center">
                <span class="flex items-center text-2xl">
                    @yield('code') — @yield('message')
                </span>

                @if (\App\Utils\Ninja::isSelfHost())
                    <span class="flex items-center text-1xl">
                        Check storage/logs for more details
                    </span>
                @endif

                <a class="button-link text-sm mt-2" href="{{ url(request()->getSchemeAndHttpHost() . '/client') }}">
                    {{ ctrans('texts.back_to', ['url' => parse_url(request()->getHttpHost())['host'] ?? request()->getHttpHost()]) }}
                </a>
            </div>
        </div>
    </div>
@endsection


