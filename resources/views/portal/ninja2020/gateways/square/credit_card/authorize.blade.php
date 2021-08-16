@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server_response">
        @csrf
        <input type="text" name="sourceId" id="sourceId" hidden>
   
      <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element-single')
      <div id="card-container"></div>

      <div id="payment-status-container"></div>

    </form>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now')
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')

  @if($gateway->company_gateway->getConfigField('testMode'))
    <script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
  @else
    <script type="text/javascript" src="https://web.squarecdn.com/v1/square.js"></script>
  @endif

  <script>
    const appId = "{{ $gateway->company_gateway->getConfigField('applicationId') }}";
    const locationId = "{{ $gateway->company_gateway->getConfigField('locationId') }}";

    const darkModeCardStyle = {
      '.input-container': {
        borderColor: '#2D2D2D',
        borderRadius: '6px',
      },
      '.input-container.is-focus': {
        borderColor: '#006AFF',
      },
      '.input-container.is-error': {
        borderColor: '#ff1600',
      },
      '.message-text': {
        color: '#999999',
      },
      '.message-icon': {
        color: '#999999',
      },
      '.message-text.is-error': {
        color: '#ff1600',
      },
      '.message-icon.is-error': {
        color: '#ff1600',
      },
      input: {
        backgroundColor: '#2D2D2D',
        color: '#FFFFFF',
        fontFamily: 'helvetica neue, sans-serif',
      },
      'input::placeholder': {
        color: '#999999',
      },
      'input.is-error': {
        color: '#ff1600',
      },
    };

    async function initializeCard(payments) {
      const card = await payments.card({
        style: darkModeCardStyle,
      });
      await card.attach('#card-container');

      return card;
    }

    async function tokenize(paymentMethod) {
      const tokenResult = await paymentMethod.tokenize();
      if (tokenResult.status === 'OK') {
        return tokenResult.token;
      } else {
        let errorMessage = `Tokenization failed with status: ${tokenResult.status}`;
        if (tokenResult.errors) {
          errorMessage += ` and errors: ${JSON.stringify(
            tokenResult.errors
          )}`;
        }

        throw new Error(errorMessage);
      }
    }

    // status is either SUCCESS or FAILURE;
    function displayPaymentResults(status) {
      const statusContainer = document.getElementById(
        'payment-status-container'
      );
      if (status === 'SUCCESS') {
        statusContainer.classList.remove('is-failure');
        statusContainer.classList.add('is-success');
      } else {
        statusContainer.classList.remove('is-success');
        statusContainer.classList.add('is-failure');
      }

      statusContainer.style.visibility = 'visible';
    }

    document.addEventListener('DOMContentLoaded', async function () {
      if (!window.Square) {
        throw new Error('Square.js failed to load properly');
      }

      let payments;
      try {
        payments = window.Square.payments(appId, locationId);
      } catch {
        const statusContainer = document.getElementById(
          'payment-status-container'
        );
        statusContainer.className = 'missing-credentials';
        statusContainer.style.visibility = 'visible';
        return;
      }

      let card;
      try {
        card = await initializeCard(payments);
      } catch (e) {
        console.error('Initializing Card failed', e);
        return;
      }

      async function handlePaymentMethodSubmission(event, paymentMethod) {
        event.preventDefault();

        try {
          // disable the submit button as we await tokenization and make a payment request.
          cardButton.disabled = true;
          const token = await tokenize(paymentMethod);

          document.getElementById('sourceId').value = token;
          document.getElementById('server_response').submit();
  
          displayPaymentResults('SUCCESS');

        } catch (e) {
          cardButton.disabled = false;
          displayPaymentResults('FAILURE');
          console.error(e.message);
        }
      }

      const cardButton = document.getElementById('pay-now');
      cardButton.addEventListener('click', async function (event) {
        await handlePaymentMethodSubmission(event, card);
      });
    });
  </script>

  @endsection