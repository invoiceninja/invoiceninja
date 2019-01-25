@extends('layouts.master', ['header' => $header])

@section('body')
<main class="main" id="client_show">
    
    <!-- Breadcrumb-->
    {{ Breadcrumbs::render('clients.show', $client) }}
    
    <vue-toastr ref="toastr"></vue-toastr>

    <div class="container-fluid">


            <client-show :client="{{ $client }}" :company="{{ $company }}"></client-show>



    </div>

</main>

<script defer src=" {{ mix('/js/client_show.min.js') }}"></script>

@endsection