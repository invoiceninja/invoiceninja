@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => 'PayPal'])

@section('gateway_head')
    <link
      rel="stylesheet"
      type="text/css"
      href="https://www.paypalobjects.com/webstatic/en_US/developer/docs/css/cardfields.css"
    />

@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_type_id" id="gateway_type_id" value="{{ $gateway_type_id }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}"/>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

<div id="paypal-button-container" class="paypal-button-container"></div>
   
@endsection

@section('gateway_footer')
@endsection

@push('footer')

<script src="https://www.paypal.com/sdk/js?client-id={!! $client_id !!}&components=buttons,funding-eligibility&intent=capture" data-client-token="{!! $token !!}"></script>
<div id="paypal-button-container"></div>
<script>

//&buyer-country=US&currency=USD&enable-funding=venmo
    const fundingSource = "{!! $funding_source !!}";
    const testMode = {{ $gateway->company_gateway->getConfigField('testMode') }};
    const clientId = "{{ $client_id }}";
    const sandbox = { sandbox: clientId };
    const production = { production: clientId };
    const orderId = "{!! $order_id !!}";

    paypal.Buttons({
        env: testMode ? 'sandbox' : 'production',
        fundingSource: fundingSource,
        client: testMode ? sandbox : production,
        createOrder: function(data, actions) {
            return orderId;  
        },
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                document.getElementById("gateway_response").value =JSON.stringify( details );
                document.getElementById("server_response").submit();
            });           
        },
        onCancel: function() {
            window.location.href = "/client/invoices/";
        },
        onError: function(error) {
            document.getElementById("gateway_response").value = error;
            document.getElementById("server_response").submit();
        }
    
    }).render('#paypal-button-container');
</script>

@endpush