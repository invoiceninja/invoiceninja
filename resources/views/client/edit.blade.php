@extends('layouts.master', ['header' => $header])

@section('body')
<main class="main" id="app">
    <!-- Breadcrumb-->
    {{ Breadcrumbs::render('clients.edit', $client) }}

    <div class="container-fluid" >

        <div class="row">
            <div class="col-lg-12">

            {{ html()->form('PUT', route('signup.submit'))->open() }}
    
                <example-component></example-component>
            </div>

        </div>

            {{ html()->form()->close() }}
    </div>

</main>

@endsection