@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ''])

@section('gateway_head')

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
<script src="https://www.paypal.com/sdk/js?enable-funding={!! $funding_options !!}&disable-funding=credit&components=buttons,hosted-fields,funding-eligibility&intent=capture&client-id={!! $client_id !!}" data-client-token="{!! $token !!}" data-partner-attribution-id="invoiceninja_SP_PPCP">
</script>

<script>

    paypal.Buttons({ 
    fundingSource: "{{ $funding_options }}",
    env: "{{ $gateway->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production' }}",
    client: {
        @if($gateway->company_gateway->getConfigField('testMode'))
        sandbox: "{{ $gateway->company_gateway->getConfigField('clientId') }}"
        @else
        production: "{{ $gateway->company_gateway->getConfigField('clientId') }}"
        @endif
    },       
    createOrder: function(data, actions) {
      return "{!! $order_id !!}"  
    },
    onCancel: function() {
        window.location.href = "/client/invoices/";
    },
    onApprove: function(data, actions) {

      return actions.order.capture().then(function(details) {                                    
        
          document.getElementById("gateway_response").value =JSON.stringify( details );
          document.getElementById("server_response").submit();

      });           

      },
    onError: function(err) {
          console.log(err);
    },
    onClick: function (){
            document.getElementById('paypal-button-container').hidden = true;
    }
    
    }).render('#paypal-button-container').catch(function(err) {
        
      document.getElementById('errors').textContent = err;
      document.getElementById('errors').hidden = false;
        
    })



</script>
@endpush