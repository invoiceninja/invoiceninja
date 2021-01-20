@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.apple_pay'), 'card_title' => ctrans('texts.apple_pay')])

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element-single', ['title' => ctrans('texts.apple_pay'), 'show_title' => false])
        {{ __('texts.payment_method_saving_failed') }}
    @endcomponent
@endsection
