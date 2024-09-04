<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="stripe-bank-transfer-payment">
    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    @endif

    <meta name="stripe-client-secret" content="{{ $client_secret }}" />    
    <meta name="stripe-return-url" content="{{ $return_url }}" />    
    <meta name="viewport" content="width=device-width, minimum-scale=1" />

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <form action="{{ route('client.payments.response') }}" method="post" id="payment-form">
        @csrf

        <div id="payment-element" style="padding:40px;">
            <!-- Elements will create form elements here -->
        </div>

        <div class="bg-white px-4 py-5 flex justify-end">
            <button @isset($form) form="{{ $form }}" @endisset type="submit" id="{{ $id ?? 'pay-now' }}" @isset($data)
            @foreach($data as $prop => $value) data-{{ $prop }}="{{ $value }}" @endforeach @endisset
                class="button button-primary bg-primary {{ $class ?? '' }}" {{ isset($disabled) && $disabled === true ? 'disabled' : '' }}>
                <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <span>{{ $slot ?? ctrans('texts.pay_now') }}</span>
            </button>
        </div>
    </form>
</div>

@assets
<script src="https://js.stripe.com/v3/"></script>
@vite('resources/js/clients/payments/stripe-bank-transfer.js')
@endassets