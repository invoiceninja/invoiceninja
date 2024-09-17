@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_head')
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="charge_no3d">
        <button type="submit" class="hidden" id="stub">Submit</button>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <div id="powerboard-payment-container" class="w-full">
        <div id="widget" style="block"></div>
    </div>  
    
    @component('portal.ninja2020.gateways.includes.pay_now', ['id' => 'authorize-card'])
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent

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
        var widget = new cba.HtmlWidget('#widget', '{{ $public_key }}', '{{ $gateway_id }}');
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
        
        widget.on("finish", async function(data) {
            document.getElementById('errors').hidden = true;
            console.log("finish", data);
        });

        widget.on("submit", async function (data){
            console.log("submit");
            console.log(data);        
            document.getElementById('errors').hidden = true;
        })

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

        let payNow = document.getElementById('authorize-card');

        payNow.addEventListener('click', () => {
                    
            document.querySelector(
                    'input[name="charge_no3d"]'
                ).value = true;
            payNow.disabled = true;
            payNow.querySelector('svg').classList.remove('hidden');
            payNow.querySelector('span').classList.add('hidden');
        
            document.getElementById('stub').click();

        });

    </script> 

@endsection



