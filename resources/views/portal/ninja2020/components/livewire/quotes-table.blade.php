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
                <input wire:click="statusChange('draft')" type="checkbox" class="form-checkbox">
                <label for="draft" class="text-sm">{{ ctrans('texts.status_draft') }}</label>
            </div>
            <div class="mr-3">
                <input wire:click="statusChange('sent')" value="sent" type="checkbox" class="form-checkbox">
                <label for="sent" class="text-sm">{{ ctrans('texts.status_sent') }}</label>
            </div>
            <div class="mr-3">
                <input wire:click="statusChange('approved')" value="approved" type="checkbox" class="form-checkbox">
                <label for="approved" class="text-sm">{{ ctrans('texts.approved') }}</label>
            </div>
            <div class="mr-3">
                <input wire:click="statusChange('expired')" value="expired" type="checkbox" class="form-checkbox">
                <label for="expired" class="text-sm">{{ ctrans('texts.expired') }}</label>
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
                                {{ ctrans('texts.quote_number') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('date')" class="cursor-pointer">
                                {{ ctrans('texts.quote_date') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('balance')" class="cursor-pointer">
                                {{ ctrans('texts.balance') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('date')" class="cursor-pointer">
                                {{ ctrans('texts.valid_until') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <span role="button" wire:click="sortBy('status_id')" class="cursor-pointer">
                                {{ ctrans('texts.quote_status') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($quotes as $quote)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 font-medium text-gray-900">
                                <label>
                                    <input type="checkbox" class="form-check form-check-child" data-value="{{ $quote->hashed_id }}">
                                </label>
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $quote->number }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $quote->formatDate($quote->date, $quote->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ App\Utils\Number::formatMoney($quote->balance, $quote->client) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $quote->formatDate($quote->date, $quote->client->date_format()) }} <!-- Fix valid until -->
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {!! App\Models\Quote::badgeForStatus($quote->status_id) !!}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap flex items-center justify-end text-sm leading-5 font-medium">
                                <a href="{{ route('client.quotes.show', $quote->hashed_id) }}" class="button-link">
                                    @lang('texts.view')
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500" colspan="100%">
                                {{ ctrans('texts.no_results') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="flex justify-center md:justify-between mt-6 mb-6">
        @if($quotes->total() > 0)
            <span class="text-gray-700 text-sm hidden md:block">
                {{ ctrans('texts.showing_x_of', ['first' => $quotes->firstItem(), 'last' => $quotes->lastItem(), 'total' => $quotes->total()]) }}
            </span>
        @endif
        {{ $quotes->links() }}
    </div>
</div>

@push('footer')
    <script src="{{ asset('js/clients/quotes/action-selectors.js') }}"></script>
@endpush