@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH', 'card_title' => 'ACH'])

@section('gateway_content')
<!--     @if(session()->has('ach_error'))
        <div class="alert alert-failure mb-4">
            <p>{{ session('ach_error') }}</p>
        </div>
    @endif -->

    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::BANK_TRANSFER]) }}" method="post" id="server_response">
        @csrf

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_type_id" value="2">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="is_default" id="is_default">

        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="type" id="type" value="{{ $type ?? 'ach'}}">
        <input type="hidden" name="customer" id="customer" value="{{ $customer }}">
        <input type="hidden" name="payment_intent" id="payment_intent" value="">

    <!-- </form> -->

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <div class="alert alert-warning mb-4">
        <h2>Adding a bank account here requires verification, which may take several days. In order to use Instant Verification please pay an invoice first, this process will automatically verify your bank account.</h2>
    </div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_type')])
        <span class="flex items-center mr-4">
            <input class="form-radio mr-2" type="radio" value="individual" name="business_account" checked>
            <span>{{ __('texts.individual_account') }}</span>
        </span>
        <span class="flex items-center">
            <input class="form-radio mr-2" type="radio" value="company" name="business_account">
            <span>{{ __('texts.company_account') }}</span>
        </span>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_holder_name')])
        <input class="input w-full" name="account_name" id="account_name" type="text" placeholder="{{ ctrans('texts.name') }}" required value="{{ auth()->guard('contact')->user()->client->present()->name() }}">
    @endcomponent


    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.routing_number')])
        <input class="input w-full" name="routing_number" id="routing_number" type="text" required>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.account_number')])
        <input class="input w-full" name="account_number" id="account_number" type="text" required>
    @endcomponent
    
    <!-- @component('portal.ninja2020.components.general.card-element', ['title' => 'Save Account'])
        <input class="form-radio mr-2" type="radio" value="1" name="save_account">Yes
        <input class="form-radio mr-2" type="radio" value="0" name="save_account" checked>No
    </span>
    @endcomponent -->

    @component('portal.ninja2020.components.general.card-element-single')
        <input type="checkbox" class="form-checkbox mr-1" id="accept-terms" required>
        <label for="accept-terms" class="cursor-pointer">{{ ctrans('texts.ach_authorization', ['company' => auth()->user()->company->present()->name, 'email' => auth()->guard('contact')->user()->client->company->settings->email]) }}</label>
    @endcomponent
<span id="error_message" style="margin-left: 3rem;"></span>
    <div class="bg-white px-4 py-5 flex justify-end">
    <button
        type="button"
        id="pay-now"
        class="button button-primary bg-primary">
            <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        <span>{{ $slot ?? ctrans('texts.add_payment_method') }}</span>
    </button>
</div>


    </form>
@endsection

@section('gateway_footer')

@endsection

<script type="text/javascript" src="https://code.jquery.com/jquery-1.7.1.min.js"></script>
<script type="text/javascript">

    $(document).ready(function(){

    $('#pay-now').click(function(){
        $('#error_message').text('')
        var account_number = document.querySelector('input[name="account_number"]').value;
        var routing_number = document.querySelector('input[name="routing_number"]').value;
        var customer = "{{ $customer }}";
        var params = {
            customerId: customer,
            account_validation: 'no',
            accountType: 'checking',
            accountNumber: account_number,
            routingNumber: routing_number,
        }

        $.ajax({
            headers: {
                "X-Publishable-Key": "{{ $publish_key }}",
            },
            url : "{{ $url }}",
            data : params,
            type : 'POST',
            dataType : 'json',
            success : function(data){

                // var data = JSON.parse(result);

                if(data.status){
                    $('#payment_intent').val(data.account_id)
                    $('#account_number').val(account_number.slice(-5))
                }else{
                    $('#error_message').text(data.message).css({'color':'red', "font-weight":"bold"})
                    return false;
                }

                $('#server_response').submit();

            }
        });
    })
})
</script>
