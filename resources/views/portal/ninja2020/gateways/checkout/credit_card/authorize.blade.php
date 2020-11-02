@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Checkout.com', 'card_title' => 'Checkout.com'])

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element-single', ['title' => 'Checkout.com', 'show_title' => false])
        {{ __('texts.checkout_authorize_label') }}
    @endcomponent
@endsection