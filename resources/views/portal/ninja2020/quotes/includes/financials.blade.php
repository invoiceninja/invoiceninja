@php
    $financials ??= app(App\Services\Quote\QuoteOptions::class)->financials($quote, $line_items);
@endphp

<div @class(['grid items-start gap-6 xl:grid-cols-3' => $interactive, 'flex flex-col gap-6' => ! $interactive])>
    <div @class(['flex min-w-0 flex-col gap-6', 'xl:col-span-2' => $interactive && $financials['totals'], 'xl:col-span-3' => $interactive && ! $financials['totals']])>
        @foreach($financials['groups'] as $group => $items)
            @if($items)
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" data-quote-group="{{ $group }}">
                    @if($group === 'optional')
                        <div class="flex flex-col gap-2 border-b border-blue-100 bg-blue-50 p-4 sm:p-5">
                            <h2 class="text-lg font-semibold text-gray-900">{{ ctrans('texts.optional_items') }}</h2>
                            @if($interactive)
                                <p class="text-sm leading-relaxed text-gray-700">{{ ctrans('texts.quote_options_help') }}</p>
                                <p class="text-sm font-medium text-gray-900" aria-live="polite" data-testid="quote-options-selected-count">{{ ctrans('texts.quote_options_selected', ['selected' => count($selected_options), 'total' => count($items)]) }}</p>
                            @endif
                        </div>
                    @endif
                    <div class="divide-y divide-gray-200">
                        @foreach($items as $item)
                            @php
                                $index = $item['index'];
                                $is_selected = $interactive && $group === 'optional' && in_array($index, $selected_options);
                            @endphp
                            <article @if($interactive) wire:key="quote-option-{{ $index }}" @endif data-quote-item-index="{{ $index }}" @class(['flex min-w-0 flex-col gap-4 p-4 sm:p-5', 'bg-blue-50' => $is_selected])>
                                <div class="flex items-start justify-between gap-4">
                                    <dl class="flex min-w-0 flex-1 flex-col gap-3">
                                        @foreach($item['description'] as $field)
                                            <div class="break-words" data-quote-field="{{ $field['key'] }}">
                                                <dt class="pb-1 text-xs text-gray-600">{!! $field['label'] !!}</dt>
                                                <dd @class(['text-sm leading-relaxed', 'font-semibold text-gray-900' => in_array($field['key'], ['item', 'service', 'product_key']), 'text-gray-700' => ! in_array($field['key'], ['item', 'service', 'product_key'])])>{!! $field['value'] !!}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                    @if($item['total'])
                                        <dl class="shrink-0 text-right" data-quote-field="{{ $item['total']['key'] }}">
                                            <dt class="pb-1 text-xs text-gray-600">{!! $item['total']['label'] !!}</dt>
                                            <dd class="text-base font-semibold tabular-nums text-gray-900">{!! $item['total']['value'] !!}</dd>
                                        </dl>
                                    @endif
                                </div>
                                @if($item['details'])
                                    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                                        @foreach($item['details'] as $field)
                                            <div class="min-w-0 break-words" data-quote-field="{{ $field['key'] }}">
                                                <dt class="pb-1 text-xs text-gray-600">{!! $field['label'] !!}</dt>
                                                <dd class="tabular-nums text-gray-900">{!! $field['value'] !!}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif
                                @if($interactive && $group === 'optional')
                                    <label @class(['inline-flex min-h-11 cursor-pointer items-center gap-2 self-start rounded-md border px-3 py-2 text-sm font-medium', 'border-blue-300 bg-blue-50 text-blue-900' => $is_selected, 'border-gray-300 bg-white text-gray-900' => ! $is_selected])>
                                        <input type="checkbox" wire:model.live="selected_options" value="{{ $index }}" wire:loading.attr="disabled" wire:target="selected_options" class="h-5 w-5 shrink-0 rounded border-gray-400 text-primary focus:ring-2 focus:ring-blue-500" aria-label="{{ $item['label'] }}">
                                        <span data-testid="quote-option-status-{{ $index }}">{{ ctrans($is_selected ? 'texts.included_in_total' : 'texts.add_to_quote') }}</span>
                                    </label>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>

    @if($financials['totals'])
        <aside @class(['min-w-0 rounded-lg border border-gray-200 bg-white p-5 shadow-sm', 'xl:sticky xl:top-4' => $interactive, 'w-full self-end sm:max-w-sm' => ! $interactive])>
            <h2 class="pb-5 text-base font-semibold text-gray-900">{{ ctrans('texts.quote_total') }}</h2>
            <dl class="flex flex-col gap-4 text-sm" aria-live="polite" aria-atomic="true" @if($interactive) wire:loading.class="opacity-50" wire:target="selected_options" @endif>
                @foreach($financials['totals'] as $total)
                    <div @class(['flex justify-between gap-4', 'border-t border-gray-200 pt-4 text-xl font-semibold text-gray-900' => $total['is_total']])>
                        <dt class="min-w-0 break-words text-gray-600">{!! $total['label'] !!}</dt>
                        <dd class="tabular-nums text-gray-900" data-testid="quote-options-{{ $total['key'] }}">{!! $total['value'] !!}</dd>
                    </div>
                @endforeach
            </dl>
        </aside>
    @endif
</div>
