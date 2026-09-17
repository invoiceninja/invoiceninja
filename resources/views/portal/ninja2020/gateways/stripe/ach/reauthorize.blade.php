@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH (Authorization)', 'card_title' => 'ACH (Authorization)'])

@section('gateway_head')
    @if($gateway->company_gateway->getConfigField('account_id'))
        <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $gateway->company_gateway->getPublishableKey() }}">
    @endif
    <meta name="stripe-client-secret" content="{{ $client_secret }}">
@endsection

@section('gateway_content')
    <div class="alert alert-failure mb-4" @unless($errors->any()) hidden @endunless id="errors">
        {{ $errors->first() }}
    </div>

    <form method="POST" id="server-response">
        @csrf
        <input type="hidden" name="setup_intent_id" id="setup-intent-id">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.bank_account')])
        <span>{{ $token->meta->brand ?? ctrans('texts.bank_transfer') }}</span>
        @if(isset($token->meta->last4))
            <span>**** {{ $token->meta->last4 }}</span>
        @endif
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        <input type="checkbox" class="form-checkbox mr-1" id="accept-terms" required>
        <label for="accept-terms" class="cursor-pointer">
            {{ ctrans('texts.ach_authorization', ['company' => auth()->guard('contact')->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}
        </label>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-button'])
        {{ ctrans('texts.complete_verification') }}
    @endcomponent
@endsection

@section('gateway_footer')
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        document.getElementById('authorize-button').addEventListener('click', async function () {
            const errors = document.getElementById('errors');

            if (!document.getElementById('accept-terms').checked) {
                errors.textContent = @json(ctrans('texts.ach_authorization_required'));
                errors.hidden = false;
                return;
            }

            this.disabled = true;
            this.querySelector('svg').classList.remove('hidden');
            this.querySelector('span').classList.add('hidden');
            errors.hidden = true;

            try {
                const publishableKey = document.querySelector('meta[name="stripe-publishable-key"]').content;
                const stripeAccount = document.querySelector('meta[name="stripe-account-id"]')?.content;
                const clientSecret = document.querySelector('meta[name="stripe-client-secret"]').content;
                const stripe = stripeAccount
                    ? Stripe(publishableKey, {stripeAccount})
                    : Stripe(publishableKey);
                const {setupIntent, error} = await stripe.confirmUsBankAccountSetup(clientSecret);

                if (error || setupIntent?.status !== 'succeeded') {
                    throw new Error(error?.message ?? @json(ctrans('texts.unable_to_verify_payment_method')));
                }

                document.getElementById('setup-intent-id').value = setupIntent.id;
                document.getElementById('server-response').submit();
            } catch (error) {
                errors.textContent = error?.message ?? @json(ctrans('texts.unable_to_verify_payment_method'));
                errors.hidden = false;
                this.disabled = false;
                this.querySelector('svg').classList.add('hidden');
                this.querySelector('span').classList.remove('hidden');
            }
        });
    </script>
@endsection
