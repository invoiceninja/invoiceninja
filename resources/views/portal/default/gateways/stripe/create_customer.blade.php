@extends('portal.default.gateways.authorize')

@section('credit_card')
<div class="py-md-5 ninja stripe">
  <div class="row">
    <div class="field">
      <div id="card-number" class="input empty"></div>
      <label for="card-number" data-tid="card_number_label">Card number</label>
      <div class="baseline"></div>
    </div>
  </div>
  <div class="row">
    <div class="field half-width">
      <div id="card-expiry" class="input empty"></div>
      <label for="card-expiry" data-tid="card_expiry_label">Expiration</label>
      <div class="baseline"></div>
    </div>
    <div class="field half-width">
      <div id="card-cvc" class="input empty"></div>
      <label for="card-cvc" data-tid="card_cvc_label">CVC</label>
      <div class="baseline"></div>
    </div>
  </div>

  <div id="card-errors" role="alert"></div>
</div>
@endsection

@push('scripts')
        <script type="text/javascript">

            // Create a Stripe client.
            var stripe = Stripe('{{ $gateway->getPublishableKey() }}');

            // Create an instance of Elements.
            var elements = stripe.elements();

            // Custom styling can be passed to options when creating an Element.
            // (Note that this demo uses a wider set of styles than the guide below.)
            var elementStyles = {
                base: {
                  color: '#32325D',
                  fontWeight: 500,
                  fontFamily: 'Source Code Pro, Consolas, Menlo, monospace',
                  fontSize: '16px',
                  fontSmoothing: 'antialiased',

                  '::placeholder': {
                    color: '#CFD7DF',
                  },
                  ':-webkit-autofill': {
                    color: '#e39f48',
                  },
                },
                invalid: {
                  color: '#E25950',

                  '::placeholder': {
                    color: '#FFCCA5',
                  },
                },
              };

              var elementClasses = {
                focus: 'focused',
                empty: 'empty',
                invalid: 'invalid',
              };

              var cardNumber = elements.create('cardNumber', {
                style: elementStyles,
                classes: elementClasses,
              });
              cardNumber.mount('#card-number');

              var cardExpiry = elements.create('cardExpiry', {
                style: elementStyles,
                classes: elementClasses,
              });
              cardExpiry.mount('#card-expiry');

              var cardCvc = elements.create('cardCvc', {
                style: elementStyles,
                classes: elementClasses,
              });
              cardCvc.mount('#card-cvc');


            cardNumber.addEventListener('change', function(event){
                var displayError = document.getElementById('card-errors');
                    if (event.error) {
                    displayError.textContent = event.error.message;
                    } else {
                    displayError.textContent = '';
                    }

            });

            cardExpiry.addEventListener('change', function(event){
                var displayError = document.getElementById('card-errors');
                    if (event.error) {
                    displayError.textContent = event.error.message;
                    } else {
                    displayError.textContent = '';
                    }

            });

            cardCvc.addEventListener('change', function(event){
                var displayError = document.getElementById('card-errors');
                    if (event.error) {
                    displayError.textContent = event.error.message;
                    } else {
                    displayError.textContent = '';
                    }

            });

            function releaseSubmitButton(){
                $('.payment-form').find('button').prop('disabled', false);

            }


            // Handle form submission.
            var form = document.getElementById('payment-form');
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                var options = {
                    billing_details: {
                        name: document.getElementById('first_name').value + ' ' + document.getElementById('last_name').value,
                        @if (!empty($accountGateway->show_address))
                        address: {
                            line1: $('#address1').val(),
                            line2: $('#address2').val(),
                            city: $('#city').val(),
                            state: $('#state').val(),
                            postal_code: document.getElementById('postal_code')?$('#postal_code').val():null,
                            country: $("#country_id option:selected").attr('data-iso_3166_2')
                        }
                        @endif
                    }
                };

                @if(request()->capture)
                stripe.handleCardSetup('{{$gateway->driver()->getSetupIntent()->client_secret}}', cardNumber, {payment_method_data: options}).then(function (result) {
                    if (result.error) {
                        // Inform the user if there was an error.
                        var errorElement = document.getElementById('card-errors');
                        errorElement.textContent = result.error.message;
                        releaseSubmitButton();
                    } else {
                        // Send the ID to your server.
                        stripePaymentMethodHandler(result.setupIntent.payment_method);
                    }
                });
                @else
                stripe.createPaymentMethod('card', cardNumber, options).then(function (result) {
                    if (result.error) {
                      // Inform the user if there was an error.
                      var errorElement = document.getElementById('card-errors');
                      errorElement.textContent = result.error.message;
                        releaseSubmitButton();
                    } else {
                      // Send the ID to your server.
                      stripePaymentMethodHandler(result.paymentMethod.id);
                    }
                });
                @endif
            });


            function stripePaymentMethodHandler(paymentMethodID) {
              // Insert the token ID into the form so it gets submitted to the server
              var form = document.getElementById('payment-form');
              var hiddenInput = document.createElement('input');
              hiddenInput.setAttribute('type', 'hidden');
              hiddenInput.setAttribute('name', 'paymentMethodID');
              hiddenInput.setAttribute('value', paymentMethodID);
              form.appendChild(hiddenInput);

              // Submit the form
              form.submit();
            }

        </script>
@endpush

@push('css')
        <style>
    .ninja.stripe {
        background-color: #fff;
    }

    .ninja.stripe * {
        font-family: Source Code Pro, Consolas, Menlo, monospace;
        font-size: 16px;
        font-weight: 500;
    }

    .ninja.stripe .row {
        display: -ms-flexbox;
        display: flex;
        margin: 0 5px 10px;
    }

    .ninja.stripe .field {
        position: relative;
        width: 100%;
        height: 50px;
        margin: 0 10px;
    }

    .ninja.stripe .field.half-width {
        width: 50%;
    }

    .ninja.stripe .field.quarter-width {
        width: calc(25% - 10px);
    }

    .ninja.stripe .baseline {
        position: absolute;
        width: 100%;
        height: 1px;
        left: 0;
        bottom: 0;
        background-color: #cfd7df;
        transition: background-color 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .ninja.stripe label {
        position: absolute;
        width: 100%;
        left: 0;
        bottom: 8px;
        color: #cfd7df;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        transform-origin: 0 50%;
        cursor: text;
        transition-property: color, transform;
        transition-duration: 0.3s;
        transition-timing-function: cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .ninja.stripe .input {
        position: absolute;
        width: 100%;
        left: 0;
        bottom: 0;
        padding-bottom: 7px;
        color: #32325d;
        background-color: transparent;
    }

    .ninja.stripe .input::-webkit-input-placeholder {
        color: transparent;
        transition: color 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .ninja.stripe .input::-moz-placeholder {
        color: transparent;
        transition: color 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .ninja.stripe .input:-ms-input-placeholder {
        color: transparent;
        transition: color 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .ninja.stripe .input.StripeElement {
        opacity: 0;
        transition: opacity 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        will-change: opacity;
    }

    .ninja.stripe .input.focused,
    .ninja.stripe .input:not(.empty) {
        opacity: 1;
    }

    .ninja.stripe .input.focused::-webkit-input-placeholder,
    .ninja.stripe .input:not(.empty)::-webkit-input-placeholder {
        color: #cfd7df;
    }

    .ninja.stripe .input.focused::-moz-placeholder,
    .ninja.stripe .input:not(.empty)::-moz-placeholder {
        color: #cfd7df;
    }

    .ninja.stripe .input.focused:-ms-input-placeholder,
    .ninja.stripe .input:not(.empty):-ms-input-placeholder {
        color: #cfd7df;
    }

    .ninja.stripe .input.focused + label,
    .ninja.stripe .input:not(.empty) + label {
        color: #aab7c4;
        transform: scale(0.85) translateY(-25px);
        cursor: default;
    }

    .ninja.stripe .input.focused + label {
        color: #24b47e;
    }

    .ninja.stripe .input.invalid + label {
        color: #ffa27b;
    }

    .ninja.stripe .input.focused + label + .baseline {
        background-color: #24b47e;
    }

    .ninja.stripe .input.focused.invalid + label + .baseline {
        background-color: #e25950;
    }

    .ninja.stripe input, .ninja.stripe button {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        outline: none;
        border-style: none;
    }

    .ninja.stripe input:-webkit-autofill {
        -webkit-text-fill-color: #e39f48;
        transition: background-color 100000000s;
        -webkit-animation: 1ms void-animation-out;
    }

    .ninja.stripe .StripeElement--webkit-autofill {
        background: transparent !important;
    }

    .ninja.stripe input, .ninja.stripe button {
        -webkit-animation: 1ms void-animation-out;
    }

    .ninja.stripe button {
        display: block;
        width: calc(100% - 30px);
        height: 40px;
        margin: 40px 15px 0;
        background-color: #24b47e;
        border-radius: 4px;
        color: #fff;
        text-transform: uppercase;
        font-weight: 600;
        cursor: pointer;
    }

    .ninja.stripe input:active {
        background-color: #159570;
    }

    .ninja.stripe .error svg {
        margin-top: 0 !important;
    }

    .ninja.stripe .error svg .base {
        fill: #e25950;
    }

    .ninja.stripe .error svg .glyph {
        fill: #fff;
    }

    .ninja.stripe .error .message {
        color: #e25950;
    }

    .ninja.stripe .success .icon .border {
        stroke: #abe9d2;
    }

    .ninja.stripe .success .icon .checkmark {
        stroke: #24b47e;
    }

    .ninja.stripe .success .title {
        color: #32325d;
        font-size: 16px !important;
    }

    .ninja.stripe .success .message {
        color: #8898aa;
        font-size: 13px !important;
    }

    .ninja.stripe .success .reset path {
        fill: #24b47e;
    }
</style>
@endpush