<form action="{{ route('client.payments.credit_response') }}" method="post" id="credit-payment">
    @csrf
    <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
</form>

@component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-with-credit', 'form' => 'credit-payment'])
    {{ ctrans('texts.pay_with_credit') }}
@endcomponent
