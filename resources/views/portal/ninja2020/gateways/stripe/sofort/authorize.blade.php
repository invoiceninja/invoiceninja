@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.bank_account'), 'card_title' => ctrans('texts.bank_account')])

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element-single', ['title' => ctrans('texts.bank_account'), 'show_title' => false])
        {{ __('texts.sofort_authorize_label') }}
    @endcomponent
@endsection
