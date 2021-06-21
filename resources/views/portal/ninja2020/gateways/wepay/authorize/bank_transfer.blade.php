@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.bank_transfer')])

@section('gateway_head')

@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}"
          method="post" id="server_response">
        @csrf

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="2">
        <input type="hidden" name="is_default" id="is_default">
        <input type="hidden" name="bank_account_id" id="bank_account_id">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

@endsection

@section('gateway_footer')

<script type="text/javascript" src="https://static.wepay.com/min/js/tokenization.4.latest.js"></script>

<script type="text/javascript">
    (function() {
        @if(config('ninja.wepay.environment') == 'staging')
        WePay.set_endpoint("stage"); 
        @else
        WePay.set_endpoint("production");
        @endif

        window.onload = function(){

            response = WePay.bank_account.create({
                'client_id': "{{ config('ninja.wepay.client_id') }}",
                'email': "{{ $contact->email }}"
            }, function(data){
                if(data.error) {
                    console.log("Pop-up closing: ")
                    errors.textContent = '';
                    errors.textContent = data.error_description;
                    errors.hidden = false;
                    } else {
                    // call your own app's API to save the token inside the data;
                    // show a success page
                        console.log(data);
                        document.querySelector('input[name="bank_account_id"]').value = data.bank_account_id;                      
                        document.getElementById('server_response').submit();
                    }
                }, function(data){
                    if(data.error) {
                       console.log("Pop-up opening: ");
                       console.log(data);
                       // handle error response
                        errors.textContent = '';
                        errors.textContent = data.error_description;
                        errors.hidden = false;
                       } else {
                   // call your own app's API to save the token inside the data;
                   // show a success page
                 }
             }
             );
        };

    })();
</script>

@endsection