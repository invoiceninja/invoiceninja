@extends('portal.ninja2020.layout.clean')

@section('body')
    @livewire('billing-portal-purchase', ['billing_subscription' => $billing_subscription])
@stop
