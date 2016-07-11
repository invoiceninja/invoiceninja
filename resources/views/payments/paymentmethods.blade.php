@extends('public.header')

@section('content')
    <div class="container main-container">

        @include('payments.paymentmethods_list')

        <p></p>
    </div>
@stop
