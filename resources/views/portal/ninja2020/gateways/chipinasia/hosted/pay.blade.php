@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'CHIP', 'card_title' => 'CHIP'])

@section('gateway_content')
    <div class="flex flex-col items-center justify-center py-8">
        @if(!empty($redirect_to_gateway_url ?? null))
            <a href="{{ $redirect_to_gateway_url }}" class="button button-primary bg-primary inline-block rounded py-3 px-4 text-sm text-white">{{ ctrans('texts.pay_now') }}</a>
        @endif
    </div>
    @include('portal.ninja2020.gateways.includes.payment_details')
@endsection
