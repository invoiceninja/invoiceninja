@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Bancontact', 'card_title' =>
'Bancontact'])

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element-single')
        {{ __('texts.payment_method_cannot_be_preauthorized') }}
    @endcomponent
@endsection