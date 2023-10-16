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
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}"/>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

<div id="paypal-button-container" class="paypal-button-container"></div>
   
@endsection

@section('gateway_footer')
@endsection

@push('footer')

 <!-- Replace "test" with your own sandbox Business account app client ID -->
        <script src="https://www.paypal.com/sdk/js?client-id={!! $client_id !!}&components=buttons,funding-eligibility&intent=capture" data-client-token="{!! $token !!}"></script>
        <div id="paypal-button-container"></div>
        <script>

        paypal.Buttons({
            env: "{{ $gateway->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production' }}",
            fundingSource: "card",
            client: {
                @if($gateway->company_gateway->getConfigField('testMode'))
                sandbox: "{{ $gateway->company_gateway->getConfigField('clientId') }}"
                @else
                production: "{{ $gateway->company_gateway->getConfigField('clientId') }}"
                @endif
            },  
            // Order is created on the server and the order id is returned
            createOrder: function(data, actions) {
            return "{!! $order_id !!}"  
            },
            // Finalize the transaction on the server after payer approval
            onApprove: function(data, actions) {

      return actions.order.capture().then(function(details) {                                    
        
          document.getElementById("gateway_response").value =JSON.stringify( details );
          document.getElementById("server_response").submit();

      });           

      },
    onError: function(err) {
          console.log(err);
    }
    
    }).render('#paypal-button-container');


    </script>

@endpush