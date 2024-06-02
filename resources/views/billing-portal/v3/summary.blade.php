<div class="space-y-4">
    <h1 class="text-2xl">{{ ctrans('texts.order') }}</h1>

    @isset($this->context['bundle'])
    <div class="space-y-2">
        @foreach($this->items() as $item)
            @if($item['quantity'] > 0)
                <div class="flex justify-between text-sm">
                    <span>{{  $item['quantity'] }}x {{ $item['product_key'] }}</span>
                    <span>{{ $item['total'] }}</span>
                </div>
            @endif
        @endforeach
    </div>

    <div class="space-y-2 mt-4 border-t pt-2">
        <div class="flex justify-between text-sm">
            <span class="uppercase">{{ ctrans('texts.one_time_purchases') }}</span>
            <span>{{ $this->oneTimePurchasesTotal() }}</span>
        </div>

        <div class="flex justify-between text-sm">
            <span class="uppercase">{{ ctrans('texts.recurring_purchases') }}</span>
            <span>{{ $this->recurringPurchasesTotal() }}</span>
        </div>

        <div
            class="flex justify-between text-sm uppercase border-t pt-2"
        >
            <span>{{ ctrans('texts.total') }}</span>
            <span class="font-semibold">{{ $this->total() }}</span>
        </div>
    </div>
    @endif
</div>
