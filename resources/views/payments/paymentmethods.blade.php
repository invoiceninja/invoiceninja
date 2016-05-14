@extends('public.header')

@section('content')
    <div class="container main-container">
        <h3>{{ $title }}</h3>
        @include('payments.paymentmethods_list')
        <p></p>
    </div>
@stop