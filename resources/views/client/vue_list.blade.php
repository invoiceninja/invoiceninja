@extends('layouts.master', ['header' => $header])

@section('head')

@endsection

@section('body')
    @parent
    <main class="main" >
        <!-- Breadcrumb-->
        {{ Breadcrumbs::render('clients') }}

        <div class="container-fluid" id="client_list">

             <div class="row">
                <div class="col-md-12">
                    <vuetable-multi-select></vuetable-multi-select>
                
                    <vuetable-query-filter></vuetable-query-filter>
                
                    <button class="btn btn-primary btn-lg pull-right">{{ trans('texts.new_client') }}</button>
                </div>
            </div>

            <div style="padding-top:20px;">
                <div class="animated fadeIn">
                    <div class="col-md-12 card">

                       <client-list></client-list>

                    </div>
                </div>
            </div>
        </div>
    </main>

    <script defer src=" {{ mix('/js/client_list.min.js') }}"></script>

@endsection

@section('footer')
    
@endsection