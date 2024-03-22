<div>
    <h1 class="text-2xl">{{ ctrans('texts.payment_methods') }}</h1>

    <div class="flex flex-col space-y-3 my-3">
        @foreach($methods as $method)
            <button class="flex items-center justify-between mb-4 bg-white rounded px-6 py-4 shadow-sm border" wire:click="handleSelect('{{ $method['company_gateway_id'] }}', '{{ $method['gateway_type_id'] }}'); $wire.$refresh();">
                {{ $method['label'] }}
            </button>
        @endforeach
    </div>
</div>
