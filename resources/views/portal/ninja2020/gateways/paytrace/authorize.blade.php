@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
@endsection

@section('gateway_content')
    
    @if(!Request::isSecure())
        <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
    @endif

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <div class="w-screen items-center">

        <div id='pt_hpf_form'><!--iframe sensitive data payment fields inserted here--></div>

    </div>

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" id="ProtectForm">

    <div class="bg-white px-4 py-5 flex justify-end">
        <button
            type="submit"
            id="{{ $id ?? 'pay-now' }}"
            class="button button-primary bg-primary {{ $class ?? '' }}">
            <span>{{ ctrans('texts.add_payment_method') }}</span>
        </button>
    </div>

    </form>
@endsection

@section('gateway_footer')

<script src='https://protect.paytrace.com/js/protect.min.js'></script>

<script>

  // Minimal Protect.js setup call
  PTPayment.setup({
  
    authorization: { clientKey: "{!! $client_key !!}" }
  }).then(function(instance){
      //use instance object to process and tokenize sensitive data payment fields.
PTPayment.theme('above the line');

  // this can be any event we chose. We will use the submit event and stop any default event handling and prevent event handling bubbling.
document.getElementById("ProtectForm").addEventListener("submit",function(e){
 e.preventDefault();
 e.stopPropagation();

// To trigger the validation of sensitive data payment fields within the iframe before calling the tokenization process:
PTPayment.validate(function(validationErrors) {
 if (validationErrors.length >= 1) {
  if (validationErrors[0]['responseCode'] == '35') {
   // Handle validation Errors here
   // This is an example of using dynamic styling to show the Credit card number entered is invalid
   PTPayment.style({'cc': {'border_color': 'red'}});
  }
 } else {
   // no error so tokenize
   instance.process()
   .then( (r) => submitPayment(r) )
   .catch( (err) => handleError(err) );
 }
});// end of PTPayment.validate
});// end of add event listener submit

  });




</script>

@endsection
