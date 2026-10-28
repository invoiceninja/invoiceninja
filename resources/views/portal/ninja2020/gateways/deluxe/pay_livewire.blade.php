{{-- Fragment for Livewire/Flow2: do not extend layout.payments or the full layout is nested inside the invoice page. --}}
<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="deluxe-payment">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ $payment_method_id == \App\Models\GatewayType::BANK_TRANSFER ? ctrans('texts.bank_transfer') : ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element-single')
        <div class="flex flex-col items-center justify-center py-4">
            <p class="mb-4">{{ ctrans('texts.redirecting') }}</p>
            <a href="{{ $payment_link_url }}" class="button button-primary bg-primary inline-block rounded py-3 px-4 text-sm text-white">{{ ctrans('texts.pay_now') }}</a>
        </div>
    @endcomponent
</div>

@script
    <script>
        window.location.replace(@js($payment_link_url));
    </script>
@endscript
