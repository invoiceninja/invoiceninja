@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Revolut', 'card_title' => 'Revolut'])

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        Revolut
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    <div class="flex flex-col items-center justify-center py-8">
        <a href="{{ $checkout_url }}" id="pay-now" class="button button-primary bg-primary inline-block rounded py-3 px-4 text-sm text-white">{{ ctrans('texts.pay_now') }}</a>
    </div>
@endsection
