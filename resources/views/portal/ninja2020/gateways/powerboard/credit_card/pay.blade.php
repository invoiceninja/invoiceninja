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
        <input type="hidden" name="browser_details">
        <input type="hidden" name="charge">
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
        <div id="widget-3dsecure"></div>
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
        // widget.interceptSubmitForm('#server-response');
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

            console.log("finish", data);
        
            try {
                const resource = await get3dsToken();
                console.log("3DS Token:", resource);

                console.log("pre canvas");
                console.log(resource._3ds.token);
                
                var canvas = new cba.Canvas3ds('#widget-3dsecure', resource._3ds.token);        
                canvas.load();
            
                let widget = document.getElementById('widget');
                widget.classList.add('hidden');


            } catch (error) {
                console.error("Error fetching 3DS Token:", error);
            }

            

            canvas.on("chargeAuthSuccess", function(data) {
                console.log(data);

                document.querySelector(
                    'input[name="browser_details"]'
                ).value = null;
                
                document.querySelector(
                    'input[name="charge"]'
                ).value = JSON.stringify(data);

                let storeCard = document.querySelector(
                    'input[name=token-billing-checkbox]:checked'
                );

                if (storeCard) {
                    document.getElementById('store_card').value = storeCard.value;
                }
                
                document.getElementById('server-response').submit();

            });

            canvas.on("chargeAuthReject", function(data) {
                console.log(data);
            });


            canvas.load();

        });

        widget.on("submit", async function (data){
            console.log("submit");
            console.log(data);        
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


        async function get3dsToken() {

            const browserDetails = {
                name: navigator.userAgent.substring(0, 100), // The full user agent string, which contains the browser name and version
                java_enabled: navigator.javaEnabled() ? "true" : "false", // Indicates if Java is enabled in the browser
                language: navigator.language || navigator.userLanguage, // The browser language
                screen_height: window.screen.height.toString(), // Screen height in pixels
                screen_width: window.screen.width.toString(), // Screen width in pixels
                time_zone: (new Date().getTimezoneOffset() * -1).toString(), // Timezone offset in minutes (negative for behind UTC)
                color_depth: window.screen.colorDepth.toString() // Color depth in bits per pixel
            };
            
            document.querySelector(
                'input[name="browser_details"]'
            ).value = JSON.stringify(browserDetails);

            const formData = JSON.stringify(Object.fromEntries(new FormData(document.getElementById("server-response"))));

            try {
                // Return the fetch promise to handle it externally
                const response = await fetch('{{ route('client.payments.response') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": 'application/json',
                        "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                })

                    if (!response.ok) {

                        return await response.json().then(errorData => {      
                            throw new Error(errorData.message ?? 'Unknown error.');
                        });

                        // const text = await response.text();
                        // throw new Error(`Network response was not ok: ${response.statusText}. Response text: ${text}`);
                    }

                    return await response.json()

            }
            catch(error) {
                
                console.error('Fetch error:', error); // Log error for debugging
                throw error; //
           
            }
        }
    </script> 

@endsection



