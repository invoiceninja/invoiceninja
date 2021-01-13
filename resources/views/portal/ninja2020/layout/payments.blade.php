@extends('portal.ninja2020.layout.app')

@isset($gateway_title)
    @section('meta_title', $gateway_title)
@else
    @section('meta_title', ctrans('texts.pay_now'))
@endisset

@push('head')
    @yield('gateway_head')
@endpush

@section('body')
    @livewire('required-client-info', ['fields' => $gateway->getClientRequiredFields(), 'contact' => auth('contact')->user()])

    <div class="container mx-auto grid grid-cols-12 hidden" data-ref="gateway-container">
        <div class="col-span-12 lg:col-span-6 lg:col-start-4 overflow-hidden bg-white shadow rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                @isset($card_title)
                    <h3 class="text-lg font-medium leading-6 text-gray-900">
                        {{ $card_title }}
                    </h3>
                @endisset

                @isset($card_description)
                    <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500">
                        {{ $card_description }}
                    </p>
                @endisset
            </div>
            <div>
                @yield('gateway_content')
            </div>
        </div>
    </div>
@endsection

@push('footer')
    @yield('gateway_footer')

    <script>
        Livewire.on('passed-required-fields-check', () => {
            document.querySelector('div[data-ref="required-fields-container"]').classList.add('hidden');
            document.querySelector('div[data-ref="gateway-container"]').classList.remove('hidden');
        });
    </script>
@endpush
