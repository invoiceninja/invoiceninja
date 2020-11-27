@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Credit card', 'card_title' => 'Credit card'])

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element-single', ['title' => 'Credit card', 'show_title' => false])
        {{ __('texts.checkout_authorize_label') }}
    @endcomponent
@endsection