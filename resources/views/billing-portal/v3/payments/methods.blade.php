<div>
    <h1 class="text-2xl">{{ ctrans('texts.payment_methods') }}</h1>

    @foreach($methods as $method)
        <button wire:click="handleSelect('{{ $method['company_gateway_id'] }}', '{{ $method['gateway_type_id'] }}')">
            {{ $method['label'] }}
        </button>
    @endforeach
</div>
