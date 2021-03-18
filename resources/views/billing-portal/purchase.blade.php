@extends('portal.ninja2020.layout.clean')
@section('meta_title', $billing_subscription->product->product_key)

@section('body')
    @livewire('billing-portal-purchase', ['billing_subscription' => $billing_subscription, 'contact' => auth('contact')->user(), 'hash' => $hash, 'request_data' => $request_data])
@stop

@push('footer')
    <script>
        function updateGatewayFields(companyGatewayId, paymentMethodId) {
            document.getElementById('company_gateway_id').value = companyGatewayId;
            document.getElementById('payment_method_id').value = paymentMethodId;
        }

        Livewire.on('beforePaymentEventsCompleted', () => document.getElementById('payment-method-form').submit());
    </script>
@endpush
