@extends('layouts.master')

@section('head')
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <script src="//cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
@endsection

@section('header')
    @include('header')

    @parent
@endsection


@section('sidebar')
    @include('sidebar')
@endsection

@section('body')
    <main class="main" >
        <!-- Breadcrumb-->
        {{ Breadcrumbs::render('clients') }}

        <div class="container-fluid">
            <div id="ui-view">
                <div class="animated fadeIn">
                    <div class="row col-lg-12 card">

                        {!! $html->table() !!}

                    </div>
                </div>
            </div>
        </div>
    </main>



    @include('dashboard.aside')

@endsection

@section('footer')
    @include('footer')


    {!! $html->scripts() !!}

@endsection




