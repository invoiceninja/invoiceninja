<div>
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
    <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="align-middle inline-block min-w-full overflow-hidden rounded">
            <table class="min-w-full shadow rounded border border-gray-200 mt-4 credits-table">
                <thead>
                <tr>
                    <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-white uppercase border-b border-gray-200 bg-primary">
                        <p role="button" wire:click="sortBy('number')" class="cursor-pointer">
                            {{ ctrans('texts.subscription') }}
                        </p>
                    </th>
                    <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-white uppercase border-b border-gray-200 bg-primary">
                        <p role="button" wire:click="sortBy('number')" class="cursor-pointer">
                            {{ ctrans('texts.frequency') }}
                        </p>
                    </th>
                    <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-white uppercase border-b border-gray-200 bg-primary">
                        <p role="button" wire:click="sortBy('number')" class="cursor-pointer">
                            {{ ctrans('texts.invoice') }}
                        </p>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                        <p role="button" wire:click="sortBy('amount')" class="cursor-pointer">
                            {{ ctrans('texts.amount') }}
                        </p>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                        <p role="button" wire:click="sortBy('date')" class="cursor-pointer">
                            {{ ctrans('texts.date') }}
                        </p>
                    </th>
                    <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse($recurring_invoices as $recurring_invoice)
                    <tr class="bg-white group hover:bg-gray-100">
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ $recurring_invoice->subscription->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ \App\Models\RecurringInvoice::frequencyForKey($recurring_invoice->frequency_id) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            <a href="{{ route('client.recurring_invoice.show', $recurring_invoice->hashed_id) }}"
                               class="button-link text-primary">
                                {{ $recurring_invoice->number }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ App\Utils\Number::formatMoney($recurring_invoice->amount, $recurring_invoice->client) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            {{ $recurring_invoice->translateDate($recurring_invoice->date, $recurring_invoice->client->date_format(), $recurring_invoice->client->locale()) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                            <a href="{{ route('client.recurring_invoice.show', $recurring_invoice->hashed_id) }}"
                               class="button-link text-primary">
                                {{ ctrans('texts.view') }}
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
        @if($recurring_invoices->total() > 0)
            <span class="text-gray-700 text-sm hidden md:block">
                {{ ctrans('texts.showing_x_of', ['first' => $recurring_invoices->firstItem(), 'last' => $recurring_invoices->lastItem(), 'total' => $recurring_invoices->total()]) }}
            </span>
        @endif
        {{ $recurring_invoices->links('portal/ninja2020/vendor/pagination') }}
    </div>
</div>
