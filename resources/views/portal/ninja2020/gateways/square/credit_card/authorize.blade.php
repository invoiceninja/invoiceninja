@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server_response">
        @csrf
        <input type="txt" id=HPF_Token name= HPF_Token hidden>
        <input type="txt" id=enc_key name= enc_key hidden>
        <input type="text" name="token" hidden>
   

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element-single')
            <div id="card-container"></div>
                <button id="card-button" type="button">Add</button>
            <div id="payment-status-container"></div>

         </form>
    @endcomponent

@endsection

@section('gateway_footer')

<script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>.
    <script>
        const appId = "{{ $gateway->company_gateway->getConfigField('applicationId') }}";
        const locationId = "{{ $gateway->company_gateway->getConfigField('locationId') }}";

         async function initializeCard(payments) {
           const card = await payments.card();
           await card.attach('#card-container'); 
           return card; 
         }

document.addEventListener('DOMContentLoaded', async function () {
  if (!window.Square) {
    throw new Error('Square.js failed to load properly');
  }
  const payments = window.Square.payments(appId, locationId);
  let card;
  try {
    card = await initializeCard(payments);
  } catch (e) {
    console.error('Initializing Card failed', e);
    return;
  }

  // Step 5.2: create card payment
});
    </script>

  @endsection