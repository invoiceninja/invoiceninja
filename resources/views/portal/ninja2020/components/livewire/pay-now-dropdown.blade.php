<div>
    @unless(count($methods) == 0)
        <div x-data="{ open: false }" @keydown.window.escape="open = false" @click.outside="open = false"
             class="relative inline-block text-left" dusk="payment-methods-dropdown">
            <div>
                <div class="rounded-md shadow-sm">
                    <button dusk="pay-now-dropdown" @click="open = !open" type="button"
                            class="button button-primary bg-primary hover:bg-primary-darken inline-flex items-center">
                        {{ ctrans('texts.pay_now') }}
                        <svg class="w-5 h-5 ml-2 -mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                  d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div x-show="open" class="absolute right-0 w-56 mt-2 origin-top-right rounded-md shadow-lg">
                <div class="bg-white rounded-md ring-1 ring-black ring-opacity-5">
                    <div class="py-1">
                        @foreach($methods as $index => $method)
                            @if($method['label'] == 'Custom')
                                <a href="#" @click="open = false" dusk="pay-with-custom"
                                   data-company-gateway-id="{{ $method['company_gateway_id'] }}"
                                   data-gateway-type-id="{{ $method['gateway_type_id'] }}"
                                   data-is-paypal="{{ $method['is_paypal'] }}"
                                   class="block px-4 py-2 text-sm leading-5 text-gray-700 dropdown-gateway-button hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:bg-gray-100 focus:text-gray-900"
                                   dusk="payment-method">
                                    {{ \App\Models\CompanyGateway::find($method['company_gateway_id'])->firstOrFail()->getConfigField('name') }}
                                </a>
                            @elseif($total > 0)
                                <a href="#" @click="open = false" dusk="pay-with-{{ $index }}"
                                   data-company-gateway-id="{{ $method['company_gateway_id'] }}"
                                   data-gateway-type-id="{{ $method['gateway_type_id'] }}"
                                   data-is-paypal="{{ $method['is_paypal'] }}"
                                   class="block px-4 py-2 text-sm leading-5 text-gray-700 dropdown-gateway-button hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:bg-gray-100 focus:text-gray-900"
                                   dusk="payment-method">
                                    {{ $method['label'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endunless
</div>
