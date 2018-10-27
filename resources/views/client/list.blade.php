@extends('layouts.master')

@section('head')
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.7/css/jquery.dataTables.min.css">
    <script src="//cdn.datatables.net/1.10.7/js/jquery.dataTables.min.js"></script>
@endsection

@section('header')
    @include('header')

    @parent
≈@endsection


@section('sidebar')
    @include('sidebar')
@endsection

@section('body')
    <main class="main">
        <!-- Breadcrumb-->
        {{ Breadcrumbs::render('clients') }}
        <div class="container-fluid">

        </div>
    </main>

    

    @include('dashboard.aside')

@endsection

@section('footer')
    @include('footer')
@endsection


