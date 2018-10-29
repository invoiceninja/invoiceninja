@extends('layouts.master')

@section('header')
    @include('header')
@endsection


@section('sidebar')
    @include('sidebar')
@endsection

@section('body')
    <main class="main">
        <!-- Breadcrumb-->
            {{ Breadcrumbs::render('dashboard') }}
        <div class="container-fluid">




        </div>
    </main>

    @include('dashboard.aside')

@endsection

@section('footer')
    @include('footer')
@endsection

