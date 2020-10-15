<form action="{{ route('client.payments.credit_response') }}" method="post" id="credit-payment">
    @csrf
    <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
</form>

<div class="bg-white px-4 py-5 flex justify-end">
    <button form="credit-payment" class="button button-primary bg-primary inline-flex items-center">Pay with credit</button>
</div>
