@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="payment_form">

    <input type="hidden" id="securefieldcode" name="SecuredCardData" value="" />

    @if(!Request::isSecure())
        <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
    @endif

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <!-- This is a generic credit card component utilizing CardJS -->
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.eway.includes.credit_card')

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
<!-- Your JS includes go here -->
<script src="https://secure.ewaypayments.com/scripts/eWAY.min.js" data-init="false"></script>

<script type="text/javascript">
    var publicApiKey = "{{ $public_api_key }}";

    var fieldStyles = "line-height: 1; height: 28px; border: 1px solid #AAA; color: #000; padding: 2px;";

    var nameFieldConfig = {
            publicApiKey: publicApiKey,
            fieldDivId: "eway-secure-field-name",
            fieldType: "name",
            styles: fieldStyles 
        };
    var cardFieldConfig = {
            publicApiKey: publicApiKey,
            fieldDivId: "eway-secure-field-card",
            fieldType: "card",
            styles: fieldStyles
        };
    var expiryFieldConfig = {
            publicApiKey: publicApiKey,
            fieldDivId: "eway-secure-field-expiry",
            fieldType: "expiry",
            styles: fieldStyles
        };
    var cvnFieldConfig = {
            publicApiKey: publicApiKey,
            fieldDivId: "eway-secure-field-cvn",
            fieldType: "cvn",
            styles: fieldStyles
        };

    function secureFieldCallback(event) {
        if (!event.fieldValid) {
            console.log(event.errors);
        } else {
            // set the hidden Secure Field Code field
            var s = document.getElementById("securefieldcode");
            s.value = event.secureFieldCode
            console.log(s.value);
        }
    }


    window.onload = function () {
        eWAY.setupSecureField(nameFieldConfig, secureFieldCallback);
        eWAY.setupSecureField(cardFieldConfig, secureFieldCallback);
        eWAY.setupSecureField(expiryFieldConfig, secureFieldCallback);
        eWAY.setupSecureField(cvnFieldConfig, secureFieldCallback);
    };

        let payNow = document.getElementById('pay-now');

        payNow.addEventListener('click', () => {
            console.log("click");
            document.getElementById('server_response').submit();
        });


</script>

@endsection
