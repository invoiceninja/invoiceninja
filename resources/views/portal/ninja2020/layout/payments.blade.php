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
    @livewire('required-client-info', ['db' => $company->db, 'fields' => method_exists($gateway, 'getClientRequiredFields') ? $gateway->getClientRequiredFields() : [], 'contact_id' => auth()->guard('contact')->user()->id, 'countries' => $countries, 'company_id' => $company->id, 'company_gateway_id' => $gateway->company_gateway ? $gateway->company_gateway->id : $gateway->id, 'is_subscription' => request()->query('source') == 'subscriptions'])

    <div class="container mx-auto grid grid-cols-12 opacity-25 pointer-events-none" data-ref="gateway-container">
        <div class="col-span-12 lg:col-span-6 lg:col-start-4 bg-white shadow rounded-lg">
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

            @if(Request::isSecure())
                <span class="block mx-4 mb-4 text-xs inline-flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-600"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <span class="ml-1">Secure 256-bit encryption</span>
                </span>
            @endif
        </div>
    </div>
@endsection

@push('footer')
    @yield('gateway_footer')

    <script>

        document.addEventListener('livewire:init', () => {

            Livewire.on('passed-required-fields-check', () => {

                document.querySelector('div[data-ref="required-fields-container"]').classList.toggle('h-0');
                document.querySelector('div[data-ref="required-fields-container"]').classList.add('opacity-25');
                document.querySelector('div[data-ref="required-fields-container"]').classList.add('pointer-events-none');

                document.querySelector('div[data-ref="gateway-container"]').classList.remove('opacity-25');
                document.querySelector('div[data-ref="gateway-container"]').classList.remove('pointer-events-none');

                document
                    .querySelector('div[data-ref="gateway-container"]')
                    .scrollIntoView({behavior: "smooth"});
            });

            Livewire.on('update-shipping-data', (event) => {
                for (field in event) {
                    let element = document.querySelector(`input[name=${field}]`);

                    if (element) {
                        element.value = event[field];
                    }
                }
            });

        });

        document.addEventListener('DOMContentLoaded', function() {
            let toggleWithToken = document.querySelector('.toggle-payment-with-token');
            let toggleWithCard = document.querySelector('#toggle-payment-with-credit-card');

            if (toggleWithToken) {
                toggleWithToken.click();
            } else if (toggleWithCard) {
                toggleWithCard.click();
            }
        });
    </script>
@endpush
