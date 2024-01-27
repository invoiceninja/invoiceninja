<div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <span class="mr-2 text-sm hidden md:block">{{ ctrans('texts.per_page') }}</span>
            <select wire:model.live="per_page" class="form-select py-1 text-sm">
                <option>5</option>
                <option selected>10</option>
                <option>15</option>
                <option>20</option>
            </select>
        </div>
    </div>
    <div class="align-middle inline-block min-w-full overflow-hidden rounded mt-4">
        <table class="min-w-full shadow rounded border border-gray-200 payments-table">
            <thead>
                <tr>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                        <span role="button" wire:click="sortBy('number')" class="cursor-pointer">
                            {{ ctrans('texts.number') }}
                        </span>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                        <span role="button" wire:click="sortBy('date')" class="cursor-pointer">
                            {{ ctrans('texts.payment_date') }}
                        </span>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                        <span role="button" wire:click="sortBy('type_id')" class="cursor-pointer">
                            {{ ctrans('texts.payment_type_id') }}
                        </span>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                        <span role="button" wire:click="sortBy('amount')" class="cursor-pointer">
                            {{ ctrans('texts.amount') }}
                        </span>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                        <span role="button" wire:click="sortBy('transaction_reference')" class="cursor-pointer">
                            {{ ctrans('texts.transaction_reference') }}
                        </span>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                        <span role="button" wire:click="sortBy('status_id')" class="cursor-pointer">
                            {{ ctrans('texts.status') }}
                        </span>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr class="bg-white group hover:bg-gray-100">
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ $payment->number }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ $payment->translateDate($payment->date, $payment->client->date_format(), $payment->client->locale()) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ $payment->translatedType() }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {!! \App\Utils\Number::formatMoney($payment->amount > 0 ? $payment->amount : $payment->credits->sum('pivot.amount'), $payment->client) !!}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ \Illuminate\Support\Str::limit($payment->transaction_reference, 35) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {!! $payment->badgeForStatus() !!}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap flex items-center justify-end text-sm leading-5 font-medium">
                            <a href="{{ route('client.payments.show', $payment->hashed_id) }}" class="text-blue-600 hover:text-indigo-900 focus:outline-none focus:underline">
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
    <div class="flex justify-center md:justify-between mt-6 mb-6">
        @if($payments->total() > 0)
            <span class="text-gray-700 text-sm hidden md:block">
                {{ ctrans('texts.showing_x_of', ['first' => $payments->firstItem(), 'last' => $payments->lastItem(), 'total' => $payments->total()]) }}
            </span>
        @endif
        {{ $payments->links('portal/ninja2020/vendor/pagination') }}
    </div>
</div>
