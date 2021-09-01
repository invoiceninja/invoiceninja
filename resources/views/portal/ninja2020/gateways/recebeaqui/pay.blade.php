@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="contact-email" content="{{ $contact->email }}">
    <meta name="client-postal-code" content="{{ $contact->client->postal_code }}">

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <script src="{{ asset('js/clients/payments/card-js.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"
            integrity="sha512-pHVGpX7F/27yZ0ISY+VVjyULApbDlD0/X0rgGbTqCE7WFW5MezNTWG/dnhtbBuICzsd0WQPgpE4REBLv+UqChw=="
            crossorigin="anonymous" referrerpolicy="no-referrer">
    </script>
    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="https://sandbox.recebeaqui.com/CheckoutTransparente" method="POST" id="server_response">
        <div class="alert alert-failure mb-4" hidden id="errors"></div>
    @csrf
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ optional($token->meta)->last4 }}</span>
                </label>
            @endforeach
        @endisset

        <label>
            <input
                type="radio"
                id="toggle-payment-with-credit-card"
                class="form-radio cursor-pointer"
                name="payment-type"
                checked/>
            <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
        </label>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')
    <input type="hidden" name="Valor" value="{{ $total['amount_with_fee'] }}" />
    <input type="hidden" name="NumParcelas" value="">
    <input type="hidden" name="DescricaoPagamento" value="Pagamento de Fatura">
    <input type="hidden" name="TokenCliente" value="{{ $token_client  }}">
    <input type="hidden" name="IdPagamentoCliente" value="{{ $id_pagamento_cliente  }}">
    <input type="hidden" name="RetornoSucesso" value="{{ route('client.payments.response.get') }}">
    <input type="hidden" name="RetornoPagamentoNaoEfetuado"
           value="{{ route('client.payments.response.get', [
                        'company_gateway_id' => $gateway->getCompanyGatewayId(),
                        'payment_method_id' => '1',
                        'payment_hash' => $payment_hash->hash,
                        'amount' => $total['amount_with_fee']
                    ])
                }}">
    <input type="hidden" name="Recorrencia" value="">
    <input type="hidden" name="Tipo" value="credito">

    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6"
         style="display: flex!important; justify-content: center!important;" id="wepay--credit-card-container">
        <div class="card-js" id="my-card" data-capture-name="true">
            <input class="card-number my-custom-class" id="card_number" name="NumCartao" required>
            <input class="name" id="cardholder_name" name="NomeCartao" placeholder="{{ ctrans('texts.name')}}" required>
            <input class="expiries-at" name="DataValidade" id="expiries-at" required>
            <input class="cvc" name="CVV" id="cvv" required>
        </div>
    </div>

    @include('portal.ninja2020.gateways.includes.pay_now')

   </form>
@endsection

@section('gateway_footer')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        $('#expiries-at').mask('00/00');
        document.getElementById('pay-now').addEventListener('click', function() {
            document.getElementById('server_response').submit();
        });
    });
</script>
@endsection

