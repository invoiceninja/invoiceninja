@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ''])
@php
    $gateway_instance = $gateway instanceof \App\Models\CompanyGateway ? $gateway : $gateway->company_gateway;
    $token_billing_string = 'true';

    if($gateway_instance->token_billing == 'off' || $gateway_instance->token_billing == 'optin'){
        $token_billing_string = 'false';
    }

    if (isset($pre_payment) && $pre_payment == '1' && isset($is_recurring) && $is_recurring == '1') {
        $token_billing_string = 'true';
    }

    
@endphp
@section('gateway_head')
    <meta http-equiv="Content-Security-Policy" content="
        frame-src 'self' https://c.paypal.com https://www.sandbox.paypal.com https://www.paypal.com https://www.paypalobjects.com; 
        script-src 'self' 'unsafe-inline' 'unsafe-eval' https://c.paypal.com https://www.paypalobjects.com https://www.paypal.com https://www.sandbox.paypal.com https://www.google-analytics.com;
        img-src * data: 'self'; 
        style-src 'self' 'unsafe-inline';"
        >
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="gateway_type_id" id="gateway_type_id" value="{{ $gateway_type_id }}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="amount_with_fee" id="amount_with_fee" value="{{ $total['amount_with_fee'] }}"/>
        <input type="hidden" name="store_card" id="store_card" value="{{ $token_billing_string }}">
        <input type="hidden" name="token" value="" id="token">
    </form>

  @include('portal.ninja2020.gateways.includes.payment_details')
  
  <div class="alert alert-failure mb-4" hidden id="errors"></div>

   <div id="paypal-button-container" class="paypal-button-container"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
    <ul class="payment-method-list">
        @if (count($tokens) > 0)
            @foreach ($tokens as $token)
                <li class="payment-method-item">
                <label class="payment-method-label">
                <input type="radio" data-token="{{ $token->token }}" name="payment-type"
                            class="form-radio cursor-pointer toggle-payment-with-token" />
                        <span class="ml-1">**** {{ $token->meta?->last4 }}</span>
                    </label>
                </li>
            @endforeach
        @endisset

        <li class="payment-method-item">
            <label class="payment-method-label">
                <input type="radio" id="toggle-payment-with-credit-card" class="form-radio cursor-pointer" name="payment-type"
                    checked />
                <span class="ml-1">{{ __('texts.new_card') }}</span>
            </label>
        </li>
    </ul>

    @endcomponent
      
    <div id="checkout-form">
      <!-- Containers for Card Fields hosted by PayPal -->    
      <div id="card-number-field-container"></div>
      <div id="card-name-field-container"></div>
      <div class="expcvv" style="display:flex;">
        <div id="card-expiry-field-container" style="width:50%"></div>
        <div id="card-cvv-field-container" style="width:50%"></div>
      </div>

      @include('portal.ninja2020.gateways.includes.save_card')
      @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now'])
    </div>

    <div id="pay-now-token--container" class="hidden">
        @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now-token'])
    </div>

@endsection

@section('gateway_footer')
@endsection


@push('footer')
<script type="application/json" fncls="fnparams-dede7cc5-15fd-4c75-a9f4-36c430ee3a99">
    {
        "f":"{{ $guid }}",
        "s":"{{ $identifier }}"        
    }
</script>

<script type="text/javascript" src="https://c.paypal.com/da/r/fb.js"></script>

@if(isset($merchantId))
<script src="https://www.paypal.com/sdk/js?client-id={!! $client_id !!}&merchant-id={!! $merchantId !!}&components=card-fields" data-partner-attribution-id="invoiceninja_SP_PPCP"></script>
@else
<script src="https://www.paypal.com/sdk/js?client-id={!! $client_id !!}&components=card-fields" data-partner-attribution-id="invoiceninja_SP_PPCP"></script>
@endif
<script>

    const clientId = "{{ $client_id }}";
    const orderId = "{!! $order_id !!}";

    const cardField = paypal.CardFields({
        client: clientId,
        createOrder: function(data, actions) {
            return orderId;  
        },
        onApprove: function(data, actions) {

            const { liabilityShift, orderID } = data;
             if(liabilityShift) {
              
              /* Handle liability shift. More information in 3D Secure response parameters */
              if(liabilityShift == 'NO') {

                document.getElementById('errors').textContent = `Sorry, your transaction could not be processed, Please try a different payment method.`;
                document.getElementById('errors').hidden = false;
                return;
              }

            }

            let storeCard = document.querySelector('input[name=token-billing-checkbox]:checked');

            if (storeCard) {
                document.getElementById("store_card").value = storeCard.value;
            }

            document.getElementById("gateway_response").value =JSON.stringify( data );  
            
            formData = JSON.stringify(Object.fromEntries(new FormData(document.getElementById("server_response")))),

            fetch('{{ route('client.payments.response') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData,
            })
            .then(response => {
           
                if (!response.ok) {
                    return response.json().then(errorData => {
                        throw new Error(errorData.message);
                    });
                }
                
                return response.json();
            
            })
            .then(data => {

                var errorDetail = Array.isArray(data.details) && data.details[0];

                if (errorDetail && ['INSTRUMENT_DECLINED', 'PAYER_ACTION_REQUIRED'].includes(errorDetail.issue)) {
                    return actions.restart();
                }

                if(data.redirect){
                    window.location.href = data.redirect;
                    return;
                }

                document.getElementById("gateway_response").value =JSON.stringify( data );
                document.getElementById("server_response").submit();
            })
            .catch(error => {
                console.error('Error:', error);
                
                document.getElementById('errors').textContent = `Sorry, your transaction could not be processed...\n\n${error.message}`;
                document.getElementById('errors').hidden = false;

                document.getElementById('pay-now').disabled = false;
                document.querySelector('#pay-now > svg').classList.add('hidden');
                document.querySelector('#pay-now > span').classList.remove('hidden');
                
            });

        },
        onError: function(error) {
            if (error && typeof error === 'object' && error.details) {
                throw error;
            }

            throw new Error(typeof error === 'string' ? error : JSON.stringify(error));
        },
        onCancel: function() {
            window.location.href = "/client/invoices/{{ $invoice_hash }}";
        },
        onClick: function (){
           
        }
    
    });

  if (cardField.isEligible()) {
      
      const numberField = cardField.NumberField({
        inputEvents: {
            onChange: (event)=> {
            }
        },
      });
      
      numberField.render("#card-number-field-container");

      const cvvField = cardField.CVVField({
        inputEvents: {
            onChange: (event)=> {
            }
        },
      });
      cvvField.render("#card-cvv-field-container");

      const expiryField = cardField.ExpiryField({
        inputEvents: {
            onChange: (event)=> {
            }
        },
      });
      expiryField.render("#card-expiry-field-container");

      document.getElementById("pay-now").addEventListener('click', (e) => {
        document.getElementById('errors').textContent = '';
        document.getElementById('errors').hidden = true;
        
        document.getElementById('pay-now').disabled = true;
        document.querySelector('#pay-now > svg').classList.remove('hidden');
        document.querySelector('#pay-now > svg').classList.add('justify-center');

        document.querySelector('#pay-now > svg').classList.add('mx-auto');
        document.querySelector('#pay-now > svg').classList.add('item-center');

        document.querySelector('#pay-now > span').classList.add('hidden');

        cardField.submit().then(() => {

        }).catch((error) => {
            document.getElementById('pay-now').disabled = false;
            document.querySelector('#pay-now > svg').classList.add('hidden');
            document.querySelector('#pay-now > span').classList.remove('hidden');

            document.getElementById('errors').textContent = resolveCardFieldErrorMessage(error);
            document.getElementById('errors').hidden = false;

        });

      });

    }
  else {

  }

    function handle422Error(errorData) {
        const errorDetails = errorData?.details || [];
        return errorDetails[0] ?? null;
    }

    function parseError(error)
    {
        if (error && typeof error === 'object' && error.details) {
            return error;
        }

        const errorMessage = typeof error === 'string'
            ? error
            : (typeof error?.message === 'string' ? error.message : String(error ?? ''));

        try {
            const parsed = JSON.parse(errorMessage);
            if (parsed && typeof parsed === 'object') {
                return parsed;
            }
        } catch (e) {
        }

        const startIndex = errorMessage.indexOf('{');
        const endIndex = errorMessage.lastIndexOf('}');

        if (startIndex !== -1 && endIndex !== -1 && endIndex > startIndex) {
            try {
                return JSON.parse(errorMessage.substring(startIndex, endIndex + 1));
            } catch (e) {
            }
        }

        return null;
    }

    function isPayPalTechnicalErrorLabel(value)
    {
        return typeof value === 'string'
            && ['UNPROCESSABLE_ENTITY', 'INTERNAL_SERVER_ERROR', 'INVALID_REQUEST'].includes(value.trim());
    }

    function isCardNumberPayPalError(detail)
    {
        if (!detail) {
            return false;
        }

        const field = detail.field ?? '';
        const issue = detail.issue ?? '';
        const description = detail.description ?? '';

        return field.includes('/payment_source/card/number')
            || /invalid card number|card number/i.test(description)
            || /CARD.*NUMBER|INVALID.*NUMBER|VALIDATION_ERROR/i.test(issue);
    }

    function resolveCardFieldErrorMessage(error)
    {
        if (error?.message === 'INVALID_NUMBER') {
            return "{{ ctrans('texts.invalid_card_number') }}";
        }

        if (error?.message === 'INVALID_CVV' || error?.message === 'INVALID_EXPIRY') {
            return "{{ ctrans('texts.invalid_cvv') }}";
        }

        const parsed = parseError(error);
        const detail = handle422Error(parsed);

        if (isCardNumberPayPalError(detail)) {
            return "{{ ctrans('texts.invalid_card_number') }}";
        }

        if (detail?.description && !isPayPalTechnicalErrorLabel(detail.description)) {
            return detail.description;
        }

        if (
            isPayPalTechnicalErrorLabel(parsed?.name)
            || isPayPalTechnicalErrorLabel(error?.message)
        ) {
            return "{{ ctrans('texts.invalid_card_number') }}";
        }

        if (typeof error?.message === 'string' && error.message.length > 0) {
            const firstLine = error.message.split('\n')[0];

            if (!isPayPalTechnicalErrorLabel(firstLine)) {
                return firstLine;
            }
        }

        return "{{ ctrans('texts.payment_failed') }}";
    }

</script>


<script>

  Array
      .from(document.getElementsByClassName('toggle-payment-with-token'))
      .forEach((element) => element.addEventListener('click', (e) => {
          document
              .getElementById('save-card--container').style.display = 'none';
          document
              .getElementById('checkout-form').classList.add('hidden');
        document
              .getElementById('pay-now-token--container').classList.remove('hidden');

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
              .getElementById('checkout-form').classList.remove('hidden');

            document
              .getElementById('pay-now-token--container').classList.add('hidden');

              document
                  .getElementById('token').value = null;
          });
  }

  let payNowButton = document.getElementById('pay-now-token');

  if (payNowButton) {
      payNowButton
          .addEventListener('click', (e) => {
              
            if (token) {
                document.getElementById("token").value = token.value;
            }

                document.getElementById('errors').textContent = '';
                document.getElementById('errors').hidden = true;
                
                document.getElementById('pay-now-token').disabled = true;
                document.querySelector('#pay-now-token > svg').classList.remove('hidden');
                document.querySelector('#pay-now-token > svg').classList.add('justify-center');

                document.querySelector('#pay-now-token > svg').classList.add('mx-auto');
                document.querySelector('#pay-now-token > svg').classList.add('item-center');

                document.querySelector('#pay-now-token > span').classList.add('hidden');

            document.getElementById("gateway_response").value = JSON.stringify( {token: token.value, orderID: "{!! $order_id !!}"} );
            document.getElementById("server_response").submit();

          });
  }

</script>

@endpush