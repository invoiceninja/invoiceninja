@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.paypal'), 'card_title' => ctrans('texts.paypal')])

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element-single', ['title' => ctrans('texts.paypal'), 'show_title' => false])
        {{ __('texts.payment_method_cannot_be_authorized_first') }}
    @endcomponent
@endsection
