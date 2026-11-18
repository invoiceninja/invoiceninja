@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    @include('portal.ninja2020.gateways.revolut.credit_card.includes.form')
@endsection

@section('gateway_footer')
    <script src="{{ $embed_url }}"></script>
    @vite('resources/js/clients/payments/revolut-credit-card.js')
@endsection
