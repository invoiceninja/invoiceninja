@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')

@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="1">
        <input type="hidden" name="token" id="token"/>
        <input type="hidden" name="store_card" id="store_card"/>
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}"/>
        <input type="txt" id=HPF_Token name= HPF_Token hidden>
        <input type="txt" id=enc_key name= enc_key hidden>


    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->hashed_id }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">{{ optional($token->meta)->last4 }}</span>
                </label>
            @endforeach
        @endisset

        <label>
            <input
                type="radio"
                id="toggle-payment-with-credit-card"
                class="form-radio cursor-pointer"
                name="payment-type"
                checked/>
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    <div class="w-screen items-center" id="paytrace--credit-card-container">

        <div id='pt_hpf_form'><!--iframe sensitive data payment fields inserted here--></div>

    </div>
    @include('portal.ninja2020.gateways.includes.pay_now', ['type' => 'submit'])
    </form>
@endsection

@section('gateway_footer')

<script src='https://protect.paytrace.com/js/protect.min.js'></script>

<script>

    let token_payment = true;

    Array
    .from(document.getElementsByClassName('toggle-payment-with-token'))
    .forEach((element) => element.addEventListener('click', (e) => {
        document
            .getElementById('save-card--container').style.display = 'none';
        document
            .getElementById('paytrace--credit-card-container').style.display = 'none';

        document
            .getElementById('token').value = e.target.dataset.token;
    }));

    let payWithCreditCardToggle = document.getElementById('toggle-payment-with-credit-card');

    if (payWithCreditCardToggle) {
        payWithCreditCardToggle
            .addEventListener('click', () => {
                document
                    .getElementById('save-card--container').style.display = 'grid';
                document
                    .getElementById('paytrace--credit-card-container').style.display = 'grid';

                document
                    .getElementById('token').value = null;
                    token_payment = false;
            });
    }

    var tokens = document.getElementsByClassName('toggle-payment-with-token');
    tokens[0].click();

  // Minimal Protect.js setup call
PTPayment.setup({
  styles:
  {
   'code': {
    'font_color':'#5D99CA',
    'border_color':'#EF9F6D',
    'label_color':'#EF9F6D',
    'label_size':'20px',
    'background_color':'white',
    'border_style':'dotted',
    'font_size':'15pt',
    'height':'30px',
    'width':'100px'
 },
   'cc': {
    'font_color':'#5D99CA',
    'border_color':'#EF9F6D',
    'label_color':'#EF9F6D',
    'label_size':'20px',
    'background_color':'white',
    'border_style':'solid',
    'font_size':'15pt',
    'height':'30px',
    'width':'300px'
 },
   'exp': {
    'font_color':'#5D99CA',
    'border_color':'#EF9F6D',
    'label_color':'#EF9F6D',
    'label_size':'20px',
    'background_color':'white',
    'border_style':'dashed',
    'font_size':'15pt',
    'height':'30px',
    'width':'85px',
    'type':'dropdown'
  }
 },
  authorization: { 'clientKey': "{!! $client_key !!}" }
}).then(function(instance){


    PTPayment.getControl("securityCode").label.text("{!! ctrans('texts.cvv')!!}");
    PTPayment.getControl("creditCard").label.text("{!! ctrans('texts.card_number')!!}");
    PTPayment.getControl("expiration").label.text("{!! ctrans('texts.expires')!!}");
    //PTPayment.style({'cc': {'label_color': 'red'}});
    //PTPayment.style({'code': {'label_color': 'red'}});
    //PTPayment.style({'exp': {'label_color': 'red'}});
    //PTPayment.style({'exp':{'type':'dropdown'}});

    //PTPayment.theme('horizontal');
    // this can be any event we chose. We will use the submit event and stop any default event handling and prevent event handling bubbling.
    document.getElementById("server_response").addEventListener("submit",function(e){
    e.preventDefault();
    e.stopPropagation();

    // To trigger the validation of sensitive data payment fields within the iframe before calling the tokenization process:
    PTPayment.validate(function(validationErrors) {

    if (validationErrors.length >= 1 && token_payment == false) {

        let errors = document.getElementById('errors');

        errors.textContent = '';
        errors.textContent = validationErrors[0].description;
        errors.hidden = false;

    } else {
     // no error so tokenize
     instance.process()
     .then( (r) => {
        submitPayment(r);
        }, (err) => {
        handleError(err);
        });
    }
    });

    });

});


function handleError(err){
    console.log(err);
    document.write(JSON.stringify(err));
}

function submitPayment(r){

  var hpf_token = document.getElementById("HPF_Token");
  var enc_key = document.getElementById("enc_key");
  hpf_token.value = r.message.hpf_token;
  enc_key.value = r.message.enc_key;

  document.getElementById("server_response").submit();

}

</script>
@endsection
