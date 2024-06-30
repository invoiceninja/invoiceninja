<div class="flex flex-col space-y-4 p-4">
    @foreach($methods as $index => $method)

        <button class="button button-primary bg-primary" @click="$wire.dispatch('payment-method-selected', { company_gateway_id: {{ $method['company_gateway_id'] }}, gateway_type_id: {{ $method['gateway_type_id'] }}, amount: {{ $amount }} })">
            {{ $method['label'] }}
        </button>

    @endforeach

</div>