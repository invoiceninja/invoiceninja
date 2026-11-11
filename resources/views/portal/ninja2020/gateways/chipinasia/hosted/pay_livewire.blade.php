{{-- Fragment for Livewire/Flow2: do not extend layout.payments or the full layout is nested inside the invoice page (looped/duplicate structure). --}}
<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="chip-hosted-payment">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        CHIP
    @endcomponent

    <div class="flex flex-col items-center justify-center py-8">
        @if(!empty($redirect_to_gateway_url ?? null))
            <a href="{{ $redirect_to_gateway_url }}" class="button button-primary bg-primary inline-block rounded py-3 px-4 text-sm text-white">{{ ctrans('texts.pay_now') }}</a>
        @endif
    </div>
    @include('portal.ninja2020.gateways.includes.payment_details')
</div>
