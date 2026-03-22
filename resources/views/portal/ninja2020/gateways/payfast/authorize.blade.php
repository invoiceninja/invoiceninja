@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.credit_card'), 'card_title' => ctrans('texts.credit_card')])

@section('gateway_head')
    <meta name="contact-email" content="{{ $contact->email }}">
    <meta name="client-postal-code" content="{{ $contact->client->postal_code }}">
@endsection

@section('gateway_content')
    <form action="{{ $payment_endpoint_url }}" method="post" id="server_response">
        <input type="hidden" name="merchant_id" value="{{ $merchant_id }}">
        <input type="hidden" name="merchant_key" value="{{ $merchant_key }}">
        <input type="hidden" name="return_url" value="{{ $return_url }}">
        <input type="hidden" name="cancel_url" value="{{ $cancel_url }}">
        <input type="hidden" name="notify_url" value="{{ $notify_url }}">
        <input type="hidden" name="m_payment_id" value="{{ $m_payment_id }}">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="item_name" value="{{ $item_name }}">
        <input type="hidden" name="item_description" value="{{ $item_description}}">
        <input type="hidden" name="subscription_type" value="{{ $subscription_type }}"> 
        <input type="hidden" name="passphrase" value="{{ $passphrase }}"> 
        <input type="hidden" name="signature" value="{{ $signature }}">    

    
    @if(!Request::isSecure())
        <p class="alert alert-failure">{{ ctrans('texts.https_required') }}</p>
    @endif

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.method')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    <div class="bg-white px-4 py-5 flex justify-end">
        <button
            type="submit"
            id="{{ $id ?? 'pay-now' }}"
            class="button button-primary bg-primary {{ $class ?? '' }}">
            <span>{{ ctrans('texts.add_payment_method') }}</span>
        </button>
    </div>

   </form> 
@endsection

@section('gateway_footer')

@endsection
