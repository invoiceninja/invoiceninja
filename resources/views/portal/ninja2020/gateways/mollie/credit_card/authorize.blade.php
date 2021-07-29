@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' =>
ctrans('texts.credit_card')])

@section('gateway_head')

@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->gateway_id }}">
        {{-- <input type="hidden" name="payment_method_id" value="1"> --}}
        <input type="hidden" name="gateway_response" id="gateway_response">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        Click the "Add Payment Method" button to complete test payment.
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-card'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    <script>
        document.getElementById('authorize-card')
            .addEventListener('click', (e) => {
                document.getElementById('server_response').submit();
            });
    </script>
@endsection
