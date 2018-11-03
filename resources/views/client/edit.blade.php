@extends('layouts.master')

@section('head')
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <script src="//cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
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
        {{ Breadcrumbs::render('clients') }}

        <div class="container-fluid">

            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#tab1" role="tab">
                        Tab 1
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab2" role="tab">
                        Tab 2
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab3" role="tab">
                        Tab 3
                    </a>
                </li>
            </ul>
            <!-- Tab panes-->
            <div class="tab-content">
                <div class="tab-pane p-3 active" id="tab1" role="tabpanel">
                    Tab 1 Content
                </div>
                <div class="tab-pane p-3" id="tab2" role="tabpanel">
                    Tab 2 Content
                </div>
                <div class="tab-pane p-3" id="tab3" role="tabpanel">
                    Tab 3 Content
                </div>
            </div>

        </div>
    </main>



    @include('dashboard.aside')

@endsection

@section('footer')
    @include('footer')


@endsection











