@extends('layouts.master')

@section('head')
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <script src="//cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
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


            <table class="table table-bordered" id="clients-table">
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
    </main>



    @include('dashboard.aside')

@endsection

@section('footer')
    @include('footer')


    <script>
        $(function() {

            console.log('inside');

            $('#clients-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{!! route('datatables.data') !!}',
                columns: [
                    { data: 'id', name: 'id' },
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




