@extends('header')

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_PAYMENTS])
    @include('accounts.partials.account_gateway_wepay')
@stop