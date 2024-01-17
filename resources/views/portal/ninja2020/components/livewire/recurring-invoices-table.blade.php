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
            <table class="min-w-full shadow rounded border border-gray-200 mt-4 recurring-invoices-table">
                <thead>
                    <tr>
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('frequency_id')" class="cursor-pointer">
                                {{ ctrans('texts.frequency') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('date')" class="cursor-pointer">
                                {{ ctrans('texts.start_date') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('next_send_date')" class="cursor-pointer">
                                {{ ctrans('texts.next_send_date') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('remaining_cycles')" class="cursor-pointer">
                                {{ ctrans('texts.cycles_remaining') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary text-left text-xs leading-4 font-medium text-white uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('amount')" class="cursor-pointer">
                                {{ ctrans('texts.amount') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-primary"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                                {{ \App\Models\RecurringInvoice::frequencyForKey($invoice->frequency_id) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                                {{ $invoice->translateDate($invoice->date, $invoice->client->date_format(), $invoice->client->locale()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                                {{ $invoice->translateDate(\Carbon\Carbon::parse($invoice->next_send_date)->subSeconds($invoice->client->timezone_offset()), $invoice->client->date_format(), $invoice->client->locale()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                                {{ $invoice->remaining_cycles == '-1' ? ctrans('texts.endless') : $invoice->remaining_cycles }}
                                @if($invoice->remaining_cycles == '-1') &#8734; @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm leading-5 text-gray-500">
                                {{ \App\Utils\Number::formatMoney($invoice->amount, $invoice->client) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap flex items-center justify-end text-sm leading-5 font-medium">
                                <a href="{{ route('client.recurring_invoice.show', $invoice->hashed_id) }}" class="text-blue-600 hover:text-indigo-900 focus:outline-none focus:underline">
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
        @if($invoices->total() > 0)
            <span class="text-gray-700 text-sm hidden md:block">
                {{ ctrans('texts.showing_x_of', ['first' => $invoices->firstItem(), 'last' => $invoices->lastItem(), 'total' => $invoices->total()]) }}
            </span>
        @endif
        {{ $invoices->links('portal/ninja2020/vendor/pagination') }}
    </div>
</div>
