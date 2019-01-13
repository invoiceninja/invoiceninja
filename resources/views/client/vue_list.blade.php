@extends('layouts.master', ['header' => $header])

@section('head')

@endsection

@section('body')
    @parent
    <main class="main" >
        <!-- Breadcrumb-->
        {{ Breadcrumbs::render('clients') }}

        <div class="container-fluid" id="client_list">

            <list-actions></list-actions>

            <div style="background: #fff;">
                
                <client-list></client-list>

            </div>

        </div>
        
    </main>

    <script defer src=" {{ mix('/js/client_list.min.js') }}"></script>

@endsection

@section('footer')
    
@endsection