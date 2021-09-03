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
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="" method="POST" id="server_response">
        <div class="alert alert-failure mb-4" hidden id="errors"></div>
    @csrf
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @include('portal.ninja2020.gateways.includes.save_card')
    <div class="mercadopago-container"></div>
   </form>
@endsection

@section('gateway_footer')
<style>
    .mercadopago-container {
        display: table;
        margin: 0 auto;
    }

    .mercadopago-container button {
        width: 200px;
    }
</style>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        jQuery('#server_response').on('submit', function(e) {
            e.preventDefault();
        });
        const mp = new MercadoPago('{{ $public_key }}');

        mp.checkout({
            preference: {
                id: '{{ $preference_id }}'
            },
            render: {
                container: '.mercadopago-container',
                label: '{{ ctrans('texts.pay_now') }}',
            }
        });
    });
</script>
@endsection

