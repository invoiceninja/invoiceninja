@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'SEPA', 'card_title' => 'SEPA-Lastschrift'])

@section('gateway_head')
    @if($gateway->company_gateway->getConfigField('account_id'))
    <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
    <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
    <meta name="stripe-publishable-key" content="{{ $gateway->company_gateway->getPublishableKey() }}">
    @endif

@endsection

@section('gateway_content')

@component('portal.ninja2020.components.general.card-element-single', ['title' => 'SEPA', 'show_title' => false])
    {{ __('texts.payment_method_cannot_be_authorized_first') }}
@endcomponent

@endsection

