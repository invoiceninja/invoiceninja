@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="paytrace-client-key" content="{{ $client_key }}">
    <meta name="ctrans-cvv" content="{{ ctrans('texts.cvv') }}">
    <meta name="ctrans-card_number" content="{{ ctrans('texts.card_number') }}">
    <meta name="ctrans-expires" content="{{ ctrans('texts.expires') }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="txt" id=HPF_Token name= HPF_Token hidden>
        <input type="txt" id=enc_key name= enc_key hidden>
        <input type="text" name="token" hidden>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element-single')
        <div class="w-screen items-center" id="paytrace--credit-card-container">
            <div id="pt_hpf_form"></div>
        </div>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now')
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    @if($gateway->company_gateway->getConfigField('testMode'))
    <script src='https://protect.sandbox.paytrace.com/js/protect.min.js'></script>
    @else
    <script src='https://protect.paytrace.com/js/protect.min.js'></script>
    @endif
    <!-- <script src="{{ asset('js/clients/payments/paytrace-credit-card.js') }}"></script> -->
    {{-- @vite('resources/js/clients/payments/paytrace-credit-card.js') --}}

    <script>

    const button = document.getElementById('pay-now');


          // Minimal Protect.js setup call
    PTPayment.setup({   
    
    styles: {
                code: {
                    'font_color': '#000',
                    'border_color': '#a1b1c9',
                    'border_style': 'dotted',
                    'font_size': '13pt',
                    'input_border_radius': '2px',
                    'input_border_width': '1px',
                    'input_font': 'serif, cursive, fantasy',
                    'input_font_weight': '700',
                    'input_margin': '5px 0px 5px 20px',
                    'input_padding': '0px 5px 0px 5px',
                    'label_color': '#a0aec0',
                    'label_size': '16px',
                    'label_width': '150px',
                    'label_font': 'sans-serif, arial, serif',
                    'label_font_weight': 'bold',
                    'label_margin': '5px 0px 0px 20px',
                    'label_padding': '2px 5px 2px 5px',
                    'background_color': 'white',
                    'height': '30px',
                    'width': '150px',
                    'padding_bottom': '2px'
                },
                cc: {
                    'font_color': '#000',
                    'border_color': '#a1b1c9',
                    'border_style': 'dotted',
                    'font_size': '13pt',
                    'input_border_radius': '3px',
                    'input_border_width': '1px',
                    'input_font': 'Times New Roman, arial, fantasy',
                    'input_font_weight': '400',
                    'input_margin': '5px 0px 5px 0px',
                    'input_padding': '0px 5px 0px 5px',
                    'label_color': '#a0aec0',
                    'label_size': '16px',
                    'label_width': '150px',
                    'label_font': 'Times New Roman, sans-serif, serif',
                    'label_font_weight': 'light',
                    'label_margin': '5px 0px 0px 0px',
                    'label_padding': '0px 5px 0px 5px',
                    'background_color': 'white',
                    'height': '30px',
                    'width': '370px',
                    'padding_bottom': '0px'
                },
                exp:  {
                    'font_color': '#000',
                    'border_color': '#a1b1c9',
                    'border_style': 'dashed',
                    'font_size': '12pt',
                    'input_border_radius': '0px',
                    'input_border_width': '2px',
                    'input_font': 'arial, cursive, fantasy',
                    'input_font_weight': '400',
                    'input_margin': '5px 0px 5px 0px',
                    'input_padding': '0px 5px 0px 5px',
                    'label_color': '#a0aec0',
                    'label_size': '16px',
                    'label_width': '150px',
                    'label_font': 'arial, fantasy, serif',
                    'label_font_weight': 'normal',
                    'label_margin': '5px 0px 0px 0px',
                    'label_padding': '2px 5px 2px 5px',
                    'background_color': 'white',
                    'height': '30px',
                    'width': '85px',
                    'padding_bottom': '2px',
                    'type': 'dropdown'
                },
            },

        authorization: { clientKey: "{{ $client_key }}" }
        }).then(function(instance){
        window.instance = instance;
    });


    document.getElementById("pay-now").addEventListener("click",function(e){
    e.preventDefault();
    e.stopPropagation();

    button.querySelector('svg').classList.remove('hidden');
    button.querySelector('span').classList.add('hidden');

    e.target.parentElement.disabled = true;
    document.getElementById('errors').hidden = true;

    // To trigger the validation of sensitive data payment fields within the iframe before calling the tokenization process:
    PTPayment.validate(function(errors) {
        if (errors.length >= 1) {

            if (errors.length >= 1) {
                let errorsContainer = document.getElementById('errors');

                errorsContainer.textContent = errors[0].description;
                errorsContainer.hidden = false;

                button.querySelector('svg').classList.add('hidden');
                button.querySelector('span').classList.remove('hidden');

                return (e.target.parentElement.disabled = false);
            }
            
        } else {
        // no error so tokenize
        instance.process()
        .then( (response) => {
                document.getElementById('HPF_Token').value =
                    response.message.hpf_token;
                document.getElementById('enc_key').value =
                    response.message.enc_key;

                let tokenBillingCheckbox = document.querySelector(
                    'input[name="token-billing-checkbox"]:checked'
                );

                if (tokenBillingCheckbox) {
                    document.querySelector(
                        'input[name="store_card"]'
                    ).value = tokenBillingCheckbox.value;
                }

                document.getElementById('server_response').submit();
            })
            .catch((error) => {
                document.getElementById(
                    'errors'
                ).textContent = JSON.stringify(error);
                document.getElementById('errors').hidden = false;

                console.log(error);
            });
        }
    });
});

    </script>
@endsection
