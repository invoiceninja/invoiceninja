@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_gocardless'), 'card_title' => ctrans('texts.complete_verification')])

@section('gateway_content')
    @component('portal.ninja2020.components.general.card-element-single')
        This payment method is still in a pending state and has not yet been verified. Please contact your vendor for more information.
    @endcomponent
@endsection
