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
        <div class="flex items-center">
            <div class="mr-3">
                <input wire:click="statusChange('paid')" type="checkbox" class="form-checkbox">
                <label for="paid" class="text-sm">{{ ctrans('texts.status_paid') }}</label>
            </div>
            <div class="mr-3">
                <input wire:click="statusChange('unpaid')" type="checkbox" class="form-checkbox">
                <label for="unpaid" class="text-sm">{{ ctrans('texts.status_unpaid') }}</label>
            </div>
            <div class="mr-3">
                <input wire:click="statusChange('overdue')" type="checkbox" class="form-checkbox">
                <label for="overdue" class="text-sm">{{ ctrans('texts.overdue') }}</label>
            </div>
        </div>
    </div>
    <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="align-middle inline-block min-w-full overflow-hidden rounded">
            <table class="min-w-full shadow rounded border border-gray-200 mt-4">
                <thead>
                    <tr>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <label>
                                <input type="checkbox" class="form-check form-check-parent">
                            </label>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('number')" class="cursor-pointer">
                                {{ ctrans('texts.invoice_number') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('date')" class="cursor-pointer">
                                {{ ctrans('texts.invoice_date') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('balance')" class="cursor-pointer">
                                {{ ctrans('texts.balance') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('due_date')" class="cursor-pointer">
                                {{ ctrans('texts.due_date') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('status_id')" class="cursor-pointer">
                                {{ ctrans('texts.status') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 font-medium text-gray-900">
                                <label>
                                    <input type="checkbox" class="form-check form-check-child" data-value="{{ $invoice->hashed_id }}">
                                </label>
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $invoice->number }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $invoice->formatDate($invoice->date, $invoice->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ App\Utils\Number::formatMoney($invoice->balance, $invoice->client) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $invoice->formatDate($invoice->due_date, $invoice->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {!! App\Models\Invoice::badgeForStatus($invoice->status) !!}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap flex items-center justify-end text-sm leading-5 font-medium">
                                @if($invoice->isPayable())
                                    <button class="button button-primary py-1 px-2 text-xs uppercase mr-3 pay-now-button" data-value="{{ $invoice->hashed_id }}">
                                        @lang('texts.pay_now')
                                    </button>
                                @endif
                                <a href="{{ route('client.invoice.show', $invoice->hashed_id) }}" class="button-link">
                                    @lang('texts.view')
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="flex justify-center md:justify-between mt-6 mb-6">
        <span class="text-gray-700 text-sm hidden md:block">Showing {{ $invoices->firstItem() }} to {{ $invoices->lastItem() }} out of {{ $invoices->total() }}</span>
        {{ $invoices->links() }}
    </div>
</div>

@push('footer')
    <script src="{{ asset('js/clients/invoices/action-selectors.js') }}"></script>
@endpush