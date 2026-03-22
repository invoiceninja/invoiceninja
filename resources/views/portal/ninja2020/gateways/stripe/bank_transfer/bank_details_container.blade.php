@extends('portal.ninja2020.layout.app')

@section('meta_title', ctrans('texts.bank_transfer'))

@section('body')
@include('portal.ninja2020.gateways.stripe.bank_transfer.bank_details')
@endsection