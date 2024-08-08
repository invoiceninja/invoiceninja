<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="payfast-credit-card-payment">
    <meta name="contact-email" content="{{ $contact->email }}">
    <meta name="client-postal-code" content="{{ $contact->client->postal_code }}">

    <form action="{{ $payment_endpoint_url }}" method="post" id="server_response">
        <input type="hidden" name="merchant_id" value="{{ $merchant_id }}">
        <input type="hidden" name="merchant_key" value="{{ $merchant_key }}">
        <input type="hidden" name="return_url" value="{{ $return_url }}">
        <input type="hidden" name="cancel_url" value="{{ $cancel_url }}">
        <input type="hidden" name="notify_url" value="{{ $notify_url }}">
        <input type="hidden" name="m_payment_id" value="{{ $m_payment_id }}">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="item_name" value="{{ $item_name }}">
        <input type="hidden" name="item_description" value="{{ $item_description}}">
        <input type="hidden" name="passphrase" value="{{ $passphrase }}"> 
        <input type="hidden" name="signature" value="{{ $signature }}">    

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            @endforeach
        @endisset

        <label>
            <input
                type="radio"
                id="toggle-payment-with-credit-card"
                class="form-radio cursor-pointer"
                name="payment-type"
                checked/>
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @include('portal.ninja2020.gateways.includes.pay_now')

   </form> 
</div>

@script
<script>
    document.getElementById('pay-now').addEventListener('click', function() {
      document.getElementById('server_response').submit();
    });
</script>
@endscript