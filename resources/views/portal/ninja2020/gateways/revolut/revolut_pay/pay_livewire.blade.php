<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="revolut-pay-payment">
    @include('portal.ninja2020.gateways.revolut.revolut_pay.includes.form')
</div>

@assets
    <script src="{{ $embed_url }}"></script>
    @vite('resources/js/clients/payments/revolut-pay.js')
@endassets
