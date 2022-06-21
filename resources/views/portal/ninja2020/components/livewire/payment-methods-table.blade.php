<div>
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <span class="mr-2 text-sm hidden md:block">{{ ctrans('texts.per_page') }}</span>
            <select wire:model="per_page" class="form-select py-1 text-sm">
                <option>5</option>
                <option selected>10</option>
                <option>15</option>
                <option>20</option>
            </select>
        </div>
        <div class="relative" x-data="{ open: false }" x-on:click.away="open = false">
            <!-- Add payment method button -->
            @if($client->getCreditCardGateway() || $client->getBankTransferGateway())
                <button x-on:click="open = !open" class="button button-primary bg-primary" data-cy="add-payment-method">{{ ctrans('texts.add_payment_method') }}</button>
                <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg">
                    <div class="py-1 rounded-md bg-white ring-1 ring-black ring-opacity-5">
                        @if($client->getCreditCardGateway())
                            <a data-cy="add-credit-card-link" href="{{ route('client.payment_methods.create', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150">
                                {{ ctrans('texts.credit_card') }}
                            </a>
                        @endif
                        @if($client->getBankTransferGateway())
                            <a data-cy="add-bank-account-link" href="{{ route('client.payment_methods.create', ['method' => $client->getBankTransferMethodType()]) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150">
                                {{ ctrans('texts.bank_account') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="align-middle inline-block min-w-full overflow-hidden rounded">
            <table class="min-w-full shadow rounded border border-gray-200 mt-4 payment-methods-table">
                <thead>
                <tr>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white  uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('created_at')" class="cursor-pointer">
                                {{ ctrans('texts.created_at') }}
                            </span>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white  uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('gateway_type_id')" class="cursor-pointer">
                                {{ ctrans('texts.payment_type_id') }}
                            </span>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white  uppercase tracking-wider">
                        {{ ctrans('texts.type') }}
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white  uppercase tracking-wider">
                        {{ ctrans('texts.expires') }}
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white  uppercase tracking-wider">
                        {{ ctrans('texts.card_number') }}
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white  uppercase tracking-wider">
                        {{ ctrans('texts.default') }}
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($payment_methods as $payment_method)
                    <tr class="bg-white group hover:bg-gray-100">
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ $payment_method->formatDateTimestamp($payment_method->created_at, $client->date_format()) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ App\Models\GatewayType::getAlias($payment_method->gateway_type_id) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ ucfirst($payment_method->meta?->brand) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            @if(isset($payment_method->meta->exp_month) && isset($payment_method->meta->exp_year))
                                {{ $payment_method->meta->exp_month}} / {{ $payment_method->meta->exp_year }}
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500" data-cy="pm-last4">
                            @isset($payment_method->meta->last4)
                                **** {{ $payment_method->meta->last4 }}
                            @endisset
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            @if($payment_method->is_default)
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round" class="feather feather-check">
                                    <path d="M20 6L9 17l-5-5"/>
                                </svg>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap flex items-center justify-end text-sm leading-5 font-medium" data-cy="view-payment-method">
                            <a href="{{ route('client.payment_methods.show', $payment_method->hashed_id) }}"
                               class="text-blue-600 hover:text-indigo-900 focus:outline-none focus:underline">
                                @lang('texts.view')
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr class="bg-white group hover:bg-gray-100">
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500" colspan="100%">
                            {{ ctrans('texts.no_results') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="flex justify-center md:justify-between mt-6 mb-6">
        @if($payment_methods->total() > 0)
            <span class="text-gray-700 text-sm hidden md:block">
                {{ ctrans('texts.showing_x_of', ['first' => $payment_methods->firstItem(), 'last' => $payment_methods->lastItem(), 'total' => $payment_methods->total()]) }}
            </span>
        @endif

        {{ $payment_methods->links('portal/ninja2020/vendor/pagination') }}
    </div>
</div>
