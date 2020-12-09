@extends('portal.ninja2020.layout.payments', ['gateway_title' => $title, 'card_title' => $title])

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="response-form">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ $title }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.instructions') ])
        {!! nl2br($instructions) !!}
    @endcomponent
    
    @component('portal.ninja2020.components.general.card-element-single')
        @include('portal.ninja2020.gateways.includes.pay_now')
    @endcomponent
@endsection

@section('gateway_footer')
    <script>
        document.getElementById('pay-now').addEventListener('click', function () {
            document.getElementById('response-form').submit();
        });
    </script>
@endsection

