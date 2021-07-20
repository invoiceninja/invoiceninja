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
   styles:
  {
   'code': {
    'font_color':'#5D99CA',
    'border_color':'#EF9F6D',
    'border_style':'dotted',
    'font_size':'13pt',
    'input_border_radius':'10px',
    'input_border_width':'2px',
    'input_font':'serif, cursive, fantasy',
    'input_font_weight':'700',
    'input_margin':'5px 0px 5px 20px',
    'input_padding':'0px 5px 0px 5px',
    'label_color':'#5D99CA',
    'label_size':'16px',
    'label_width':'150px',
    'label_font':'sans-serif, arial, serif',
    'label_font_weight':'bold',
    'label_margin':'5px 0px 0px 20px',
    'label_padding':'2px 5px 2px 5px',
    'label_border_style':'dotted',
    'label_border_color':'#EF9F6D',
    'label_border_radius':'10px',
    'label_border_width':'2px',
    'background_color':'white',
    'height':'25px',
    'width':'110px',
    'padding_bottom':'2px'
   },
   'cc': {
    'font_color':'#5D99CA',
    'border_color':'#EF9F6D',
    'border_style':'solid',
    'font_size':'13pt',
    'input_border_radius':'20px',
    'input_border_width':'2px',
    'input_font':'Times New Roman, arial, fantasy',
    'input_font_weight':'400',
    'input_margin':'5px 0px 5px 0px',
    'input_padding':'0px 5px 0px 5px',
    'label_color':'#5D99CA',
    'label_size':'16px',
    'label_width':'150px',
    'label_font':'Times New Roman, sans-serif, serif',
    'label_font_weight':'light',
    'label_margin':'5px 0px 0px 0px',
    'label_padding':'0px 5px 0px 5px',
    'label_border_style':'solid',
    'label_border_color':'#EF9F6D',
    'label_border_radius':'20px',
    'label_border_width':'2px',
    'background_color':'white',
    'height':'25px',
    'width':'320px',
    'padding_bottom':'0px'
   },
   'exp': {
    'font_color':'#5D99CA',
    'border_color':'#EF9F6D',
    'border_style':'dashed',
    'font_size':'12pt',
    'input_border_radius':'0px',
    'input_border_width':'2px',
    'input_font':'arial, cursive, fantasy',
    'input_font_weight':'400',
    'input_margin':'5px 0px 5px 0px',
    'input_padding':'0px 5px 0px 5px',
    'label_color':'#5D99CA',
    'label_size':'16px',
    'label_width':'150px',
    'label_font':'arial, fantasy, serif',
    'label_font_weight':'normal',
    'label_margin':'5px 0px 0px 0px',
    'label_padding':'2px 5px 2px 5px',
    'label_border_style':'dashed',
    'label_border_color':'#EF9F6D',
    'label_border_radius':'0px',
    'label_border_width':'2px',
    'background_color':'white',
    'height':'25px',
    'width':'85px',
    'padding_bottom':'2px',
    'type':'dropdown'
   },
   'body': {
    'background_color':'white'
   }
  },    
    authorization: { clientKey: "{!! $client_key !!}" }
  }).then(function(instance){
      //use instance object to process and tokenize sensitive data payment fields.
PTPayment.theme('above the line');

  });



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
</script>

@endsection
