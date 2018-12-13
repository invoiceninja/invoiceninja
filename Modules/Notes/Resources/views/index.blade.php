@extends('layouts.master', ['header' => $header])

@section('head')
    @parent
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <script src="//cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
@endsection

@section('body')
    @parent
    <main class="main" >
        <!-- Breadcrumb-->
        {{ Breadcrumbs::render('clients') }}

        <div class="container-fluid">
            
            <div class="row">
                <div class="col-md-12">
                    <button class="btn btn-primary btn-lg pull-right">{{ trans('notes::texts.new_note') }}</button>
                </div>
            </div>

            <div id="ui-view">
                <div class="animated fadeIn" style="padding-top:20px;">
                    <div class="row col-md-12 card">

                        {!! $html->table() !!}

                    </div>
                </div>
            </div>

        </div>
    </main>
@endsection

@section('footer')
    @parent
    {!! $html->scripts() !!}
@endsection