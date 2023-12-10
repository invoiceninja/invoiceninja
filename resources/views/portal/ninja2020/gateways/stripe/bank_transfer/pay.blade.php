@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Bank Transfer', 'card_title' => 'Bank Transfer'])

@section('gateway_head')
    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey() }}">
    @endif
        <meta name="viewport" content="width=device-width, minimum-scale=1" />

@endsection

@section('gateway_content')
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <form action="{{ route('client.payments.response') }}" method="post" id="payment-form">
        @csrf
    
    <div id="payment-element" style="padding:40px;">
        <!-- Elements will create form elements here -->
    </div>

    <div class="bg-white px-4 py-5 flex justify-end">
        <button
            @isset($form) form="{{ $form }}" @endisset
            type="submit"
            id="{{ $id ?? 'pay-now' }}"
            @isset($data) @foreach($data as $prop => $value) data-{{ $prop }}="{{ $value }}" @endforeach @endisset
            class="button button-primary bg-primary {{ $class ?? '' }}"
            {{ isset($disabled) && $disabled === true ? 'disabled' : '' }}>
                <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            <span>{{ $slot ?? ctrans('texts.pay_now') }}</span>
        </button>
    </div>
    </form>

@endsection

@push('footer')
<script src="https://js.stripe.com/v3/"></script>

<script>
    const options = {
        clientSecret: '{{ $client_secret }}',
        appearance : {
            theme: 'stripe',
            variables: {
                colorPrimary: '#0570de',
                colorBackground: '#ffffff',
                colorText: '#30313d',
                colorDanger: '#df1b41',
                fontFamily: 'Ideal Sans, system-ui, sans-serif',
                spacingUnit: '2px',
                borderRadius: '4px',
                // See all possible variables below
            }
        }
    };

        
    
    const stripe = Stripe(document.querySelector('meta[name="stripe-publishable-key"]').getAttribute('content'));
    const stripeConnect = document.querySelector('meta[name="stripe-account-id"]')?.content ?? '';

    if(stripeConnect)
        stripe.stripeAccount = stripeConnect;
        
    // Set up Stripe.js and Elements to use in checkout form, passing the client secret obtained in step 3
    const elements = stripe.elements(options);
    // Create and mount the Payment Element
    const paymentElement = elements.create('payment');
    paymentElement.mount('#payment-element');


    const form = document.getElementById('payment-form');

    form.addEventListener('submit', async (event) => {
    event.preventDefault();

    document.getElementById('pay-now').disabled = true;
    document.querySelector('#pay-now > svg').classList.add('hidden');
    document.querySelector('#pay-now > span').classList.remove('hidden');

        const {error} = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: '{!! $return_url !!}',
            },

        });
            
            if (error) {
                document.getElementById('pay-now').disabled = false;
                document.querySelector('svg').classList.remove('hidden');
                document.querySelector('span').classList.add('hidden');
                const messageContainer = document.querySelector('#errors');
                messageContainer.textContent = error.message;
            } 

        
        });


</script>
@endpush