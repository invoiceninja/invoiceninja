@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.purchase'))

@section('body')
    @livewire('billing-portal-purchase', ['subscription' => $subscription->id, 'db' => $subscription->company->db, 'hash' => $hash, 'request_data' => $request_data, 'campaign' => request()->query('campaign') ?? null])
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
