@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'KBC/CBC', 'card_title' =>
'KBC/CBC'])

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element-single')
        {{ __('texts.payment_method_cannot_be_preauthorized') }}
    @endcomponent
@endsection
