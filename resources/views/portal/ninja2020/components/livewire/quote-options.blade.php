<div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    @include('portal.ninja2020.quotes.includes.financials', ['interactive' => true])

    @error('selected_options')
        <p role="alert" class="text-sm text-red-700">{{ $message }}</p>
    @enderror
    @error('selected_options.*')
        <p role="alert" class="text-sm text-red-700">{{ $message }}</p>
    @enderror

    <div class="sticky bottom-0 z-10 flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-1">
            <span class="text-xs text-gray-600">{{ ctrans('texts.quote_total') }}</span>
            <strong class="text-xl tabular-nums" data-testid="quote-options-sticky-total">{{ App\Utils\Number::formatMoney($quote->amount, $client) }}</strong>
            <span wire:loading wire:target="selected_options" role="status" class="text-xs text-gray-600">{{ ctrans('texts.updating_total') }}</span>
        </div>
        <button type="button" wire:click="continueApproval" wire:loading.attr="disabled" class="button button-primary bg-primary shrink-0">
            {{ ctrans('texts.review_quote') }}
        </button>
    </div>
</div>
