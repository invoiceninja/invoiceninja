{{-- Fragment for Livewire/Flow2: do not extend layout.payments. --}}
<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="revolut-hosted-payment">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        Revolut
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    <div class="flex flex-col items-center justify-center py-8">
        <a href="{{ $checkout_url }}" id="pay-now" class="button button-primary bg-primary inline-block rounded py-3 px-4 text-sm text-white">{{ ctrans('texts.pay_now') }}</a>
    </div>
</div>
