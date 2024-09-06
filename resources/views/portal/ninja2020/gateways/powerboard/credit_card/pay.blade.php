@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_head')
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card" id="store_card"/>
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
        <button type="submit" class="hidden" id="stub">Submit</button>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <ul class="list-none">
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
            <li class="py-2 cursor-pointer">
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            </li>
            @endforeach
        @endisset

            <li class="py-2 cursor-pointer">
                <label>
                    <input
                        type="radio"
                        id="toggle-payment-with-credit-card"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer"
                        name="payment-type"
                        checked/>
                    <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
                </label>
            </li>    
        </ul>
        

        @endcomponent

    <div id="powerboard-payment-container" class="w-full">
        <div id="widget" style="block"></div>
    </div>
    @include('portal.ninja2020.gateways.includes.save_card')
    @include('portal.ninja2020.gateways.includes.pay_now')
    
@endsection

@section('gateway_footer')

    <style>
        iframe {
            border: 0;
            width: 100%;
            height: 400px;
        }
    </style>

    <script src="{{ $widget_endpoint }}"></script>
    
    <script>
        var widget = new cba.HtmlWidget('#widget', '{{ $public_key }}', 'not_configured');
        widget.setEnv("{{ $environment }}");
        widget.useAutoResize();
        widget.interceptSubmitForm('#server-response');
        widget.onFinishInsert('input[name="gateway_response"]', "payment_source");
        widget.load();
    
        widget.trigger('tab', function (data){

            console.log("tab Response", data);

            console.log(widget.isValidForm());

            let payNow = document.getElementById('pay-now');

            payNow.disabled = widget.isInvalidForm();

        });

        widget.trigger('submit_form',function (data){

            console.log("submit_form Response", data);

            console.log(widget.isValidForm());

            let payNow = document.getElementById('pay-now');

            payNow.disabled = widget.isInvalidForm();

        });

        widget.trigger('tab',function (data){

            console.log("tab Response", data);

            console.log(widget.isValidForm());

            let payNow = document.getElementById('pay-now');

            payNow.disabled = widget.isInvalidForm();

        });

        widget.on("systemError", function(data) {
            console.log("systemError Response", data);
        });

        widget.on("validationError", function(data) {
            console.log("validationError", data);
        });


        widget.on("finish", function(data) {
            console.log("finish", data);
        });

        widget.on('form_submit', function (data) {
            
            console.log("form_submit", data);

            console.log(data);
        });

        widget.on('submit', function (data) {
            
            console.log("submit", data);

            console.log(data);
        });

        widget.on('tab', function (data) {
            
            console.log("tab", data);

            console.log(data);
        });

        let payNow = document.getElementById('pay-now');

        payNow.addEventListener('click', () => {
            
            widget.getValidationState();

            if(!widget.isValidForm()){
                console.log("invalid");
                return;
            }

            payNow.disabled = true;
            payNow.querySelector('svg').classList.remove('hidden');
            payNow.querySelector('span').classList.add('hidden');
        
            let storeCard = document.querySelector(
                'input[name=token-billing-checkbox]:checked'
            );

            if (storeCard) {
                document.getElementById('store_card').value = storeCard.value;
            }

            document.getElementById('stub').click();

        });

    </script> 

@endsection



