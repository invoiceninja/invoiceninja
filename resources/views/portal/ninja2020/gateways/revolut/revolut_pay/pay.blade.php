@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.revolut_pay'), 'card_title' => ctrans('texts.revolut_pay')])

@section('gateway_head')
    <meta name="instant-payment" content="yes" />
@endsection

@section('gateway_content')
    @include('portal.ninja2020.gateways.revolut.revolut_pay.includes.form')
@endsection

@section('gateway_footer')
    <script src="{{ $embed_url }}"></script>
    @vite('resources/js/clients/payments/revolut-pay.js')
@endsection
