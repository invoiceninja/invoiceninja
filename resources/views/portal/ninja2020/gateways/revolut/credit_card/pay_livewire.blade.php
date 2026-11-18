<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="revolut-credit-card-payment">
    @include('portal.ninja2020.gateways.revolut.credit_card.includes.form')
</div>

@assets
    <script src="{{ $embed_url }}"></script>
    @vite('resources/js/clients/payments/revolut-credit-card.js')
@endassets
