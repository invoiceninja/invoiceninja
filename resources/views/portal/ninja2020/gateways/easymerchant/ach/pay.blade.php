@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => 'ACH'])

@section('gateway_head')
        <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
        <meta name="client_secret" content="{{ $client_secret }}">
        <meta name="viewport" content="width=device-width, minimum-scale=1" />

@endsection

@section('gateway_content')
    <!-- <div class="alert alert-failure mb-4" hidden id="errors"></div> -->

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="customer" value="{{ $customer }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="gateway_response" id="gateway_response" value="">
        <input type="hidden" name="type" id="type" value="{{ $type ?? 'ACH'}}">
        <input type="hidden" name="payment_intent" id="payment_intent" value="{{ $payment_intent }}">
    

        @include('portal.ninja2020.gateways.includes.payment_details')

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
            @if(count($tokens) > 0)
            <ul class="list-none hover:list-disc">
                @foreach($tokens as $token)
                    <li class="py-1 hover:text-blue hover:bg-blue-600">
                        <label class="mr-4">
                            <input
                                onClick="toggleAccount()"
                                type="radio"
                                data-token="{{ $token->hashed_id }}"
                                name="payment-type"
                                value="{{ $token->token }}"
                                class="form-check-input text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token"/>
                            <span class="ml-1 cursor-pointer">{{ ctrans('texts.bank_transfer') }} (*{{ $token->meta->last4 }})</span>
                        </label>
                    </li>
                @endforeach
                <li class="py-1 hover:text-blue hover:bg-blue-600">
                    <input class="form-radio mr-2" type="radio" value="new_account" onclick="toggleAccount()" name="payment-type" checked>
                <span>{{ "New account" }}</span>
                </li>
            </ul>
            @else
            <ul class="list-none hover:list-disc">
                 <li class="py-1 hover:text-blue hover:bg-blue-600">
                    <input class="form-radio mr-2" type="radio" value="new_account" onclick="toggleAccount()" name="payment-type" checked>
                <span>{{ "New account" }}</span>
                </li>
            </ul>
            @endif
        @endcomponent

    </form>
        <div id="toggle-account">
            @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_type')])
                <span class="flex items-center mr-4 new_account">
                    <input class="form-radio mr-2" type="radio" value="individual" name="business_account" checked>
                    <span>{{ __('texts.individual_account') }}</span>
                </span>
                <span class="flex items-center new_account">
                    <input class="form-radio mr-2" type="radio" value="company" name="business_account">
                    <span>{{ __('texts.company_account') }}</span>
                </span>
            @endcomponent

            @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
                <input class="input w-full" name="account_name" id="account-holder-name" autocomplete="off" type="text" placeholder="{{ ctrans('texts.name') }}" required value="{{ auth()->guard('contact')->user()->client->present()->name() }}">
            @endcomponent


            @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.routing_number')])
                <input class="input w-full" name="routing_number" id="routing-number" autocomplete="off" type="text" required>
            @endcomponent

            @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_number')])
                <input class="input w-full" name="account_number" id="account-number" autocomplete="off" type="text" required>
            @endcomponent

        </div>
            @component('portal.ninja2020.components.general.card-element-single')
                <input type="checkbox" class="form-checkbox mr-1" name='accept-terms' id="accept-terms" required>
                <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}</label>
            @endcomponent
            <span id="error_message" style="margin-left: 3rem;font-size: 12px;"></span>
        <div class="bg-white px-4 py-5 flex justify-end">
            <button 
                type="button" 
                id='pay-now'
                class="button button-primary bg-primary {{ $class ?? '' }}">
                <span>{{ ctrans('texts.pay_now') }}</span>
            </button>
        </div>
        <!-- </form> -->
    @endsection

@push('footer')

@endpush

<script type="text/javascript" src="https://code.jquery.com/jquery-1.7.1.min.js"></script>
<!-- <script type="text/javascript" src="http://ajax.aspnetcdn.com/ajax/jquery.validate/1.7/jquery.validate.min.js"></script> -->

<script type="text/javascript">

    function toggleAccount() {
      var switch_account = document.getElementById('toggle-account');
      var routing_number = document.getElementById('routing-number');
      var account_number = document.getElementById('account-number');
      var account = document.querySelector('input[name="payment-type"]:checked').value;
      if (account === "new_account") {
        switch_account.style.display = 'block';
        routing_number.setAttribute('required', '');
        account_number.setAttribute('required', '');
      } else {
        switch_account.style.display = 'none';
        routing_number.removeAttribute('required');
        account_number.removeAttribute('required');
      }
    }

    $(document).ready(function(){
    $('#pay-now').click(function(){
        $('#error_message').text('')
        var routing_number = $('#routing-number').val();
        var account_number = $('#account-number').val();
        var account_name = $('#account-holder-name').val();
        var account = document.querySelector('input[name="payment-type"]:checked').value;
        var terms = document.querySelector('input[name="accept-terms"]:checked');

        if(!terms){
            $('#error_message').text("Please accept the terms to proceed.!").css({'color':'red', "font-weight":"bold"})
            return false;
        }

        if(account == 'new_account'){

            var errors = [];
            if(!account_name){
                errors.push('Accountholder name');
            }
            if(!routing_number){
                errors.push('Routing number');
            }
            if(!account_number){
                errors.push('Account number');
            }


            if(errors.length > 0){
                var message = ' (s) are required.!';
                if(errors.length == 1){
                    message = ' is required.!';
                }
                $('#error_message').text(errors.toString() + message).css({'color':'red', "font-weight":"bold"})
                return false;
            }

        // var data = JSON.parse('{"status": true, "message": "Payment Intent created successfully.!", "last_4": "1116", "payment_intent": "pi_65965f8a7a687"}');
        //     $('#payment_intent').val(data.payment_intent)
        //     $('#account-number').val(data.last_4)

        //         $('#server-response').submit();
            $.ajax({
                headers: {
                    "X-Publishable-Key": "{{ $publish_key }}",
                },
                url : "{{ $url }}",
                data : { 
                    payment_intent : "{{ $payment_intent }}", 
                    customerId: "{{ $customer }}",
                    account_validation : "no",
                    accountType : "checking",
                    accountNumber : account_number,
                    routingNumber : routing_number
                },
                type : 'POST',
                dataType : 'json',
                success : function(data){

                    // var data = JSON.parse(result);

                    if(data.status){
                        $('#payment_intent').val(data.payment_intent)
                        $('#account-number').val(data.last_4)
                    }else{
                        $('#error_message').text(data.message).css({'color':'red', "font-weight":"bold"})
                        return false;
                    }

                    $('#server-response').submit();

                }
            });
        }else{
            $('#payment_intent').val(account);
            $('#server-response').submit();
        }
    })
})
</script>