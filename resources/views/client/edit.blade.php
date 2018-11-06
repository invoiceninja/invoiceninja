@extends('layouts.master')

@section('head')
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <script src="//cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ config('ninja.google_maps_api_key') }}&callback=initMap" type="text/javascript"></script>
@endsection

@section('header')
    @include('header', $header)

    @parent
@endsection


@section('sidebar')
    @include('sidebar')
@endsection

@section('body')

<main class="main" >
    <!-- Breadcrumb-->
    {{ Breadcrumbs::render('clients.edit', $client) }}

    <div class="container-fluid">

        <div class="row">
            <div class="col-lg-12">

            {{ html()->form('PUT', route('signup.submit'))->open() }}

            
            </div>

        </div>

            {{ html()->form()->close() }}
    </div>
    
</main>



    @include('dashboard.aside')

@endsection

@section('footer')
    @include('footer')


@endsection











