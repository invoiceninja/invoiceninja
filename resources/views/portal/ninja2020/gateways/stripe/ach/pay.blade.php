@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH (Stripe)', 'card_title' => 'ACH (Stripe)'])

@section('gateway_content')
    @if($token)
        <div class="alert alert-failure mb-4" hidden id="errors"></div>
    
        @include('portal.ninja2020.gateways.includes.payment_details')

        <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
            @csrf
            <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
            <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
            <input type="hidden" name="source" value="{{ $token->token }}">
            <input type="hidden" name="amount" value="{{ $amount }}">
            <input type="hidden" name="currency" value="{{ $currency }}">
            <input type="hidden" name="customer" value="{{ $customer->id }}">
            <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        </form>

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
            {{ ctrans('texts.ach') }} ({{ ctrans('texts.bank_transfer') }}) (**** {{ $token->meta->last4 }})
        @endcomponent
    @else
        @component('portal.ninja2020.components.general.card-element-single', ['title' => 'ACH', 'show_title' => false])
            <span>{{ ctrans('texts.bank_account_not_linked') }}</span>
            <a class="button button-link text-primary" href="{{ route('client.payment_methods.index') }}">{{ ctrans('texts.add_payment_method') }}</a>
        @endcomponent
    @endif

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@push('footer')
    <script>
        document.getElementById('pay-now').addEventListener('click', function() {
            document.getElementById('server-response').submit();
        });
    </script>
@endpush
