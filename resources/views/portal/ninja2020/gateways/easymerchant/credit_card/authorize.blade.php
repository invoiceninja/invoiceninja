@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="contact-email" content="{{ $contact->email }}">
    <meta name="client-postal-code" content="{{ $contact->client->postal_code }}">

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>

    <script src="{{ asset('build/public/js/card-js.min.js/card-js.min.js') }}"></script>
    <link href="{{ asset('build/public/css/card-js.min.css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="merchant_id" value="{{ 1 }}">
        <input type="hidden" name="merchant_key" value="{{ 1 }}"> 

    
    @if(!Request::isSecure())
        <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
    @endif

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.easymerchant.includes.credit_card', ['is_required' => 'required'])

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

@endsection

<script type="text/javascript">

    console.log('test')
    var card_number = document.getElementById('card_number');
    var expiration_year = document.getElementById('expiration_year');
    var expiration_month = document.getElementById('expiration_month');
    card_number.setAttribute('required', '');
    expiration_month.setAttribute('required', '');
    expiration_year.setAttribute('required', '');

</script>