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
                        <table class="table table-striped table-bordered" id="clients-table">
                            <thead>
                            <tr>
                                <th>Id</th>
                                <th>Name</th>
                                <th>Website</th>
                                <th>Balance</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>



    @include('dashboard.aside')

@endsection

@section('footer')
    @include('footer')


    <script>
        $(function() {

            $('#clients-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{!! route('clients.index') !!}',
                columns: [
                    { data: 'id', name: 'id', visible: true },
                    { data: 'name', name: 'name' },
                    { data: 'website', name: 'website' },
                    { data: 'balance', name: 'balance' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'updated_at', name: 'updated_at' }
                ]
            });
        });
    </script>
@endsection



