@extends('portal.ninja2020.layout.payments', ['gateway_title' => $title, 'card_title' => $title])

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ $title }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element-single')
        {!! nl2br($instructions) !!}
    @endcomponent
@endsection
