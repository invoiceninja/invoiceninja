@extends('layouts.master', ['header' => $header])

@section('body')
<main class="main" id="client_show">
    
    <vue-toastr ref="toastr"></vue-toastr>

    <div class="container-fluid">


        <client-show :client="{{ $client }}" :company="{{ $company }}" :meta="{{ $meta }}"></client-show>


    </div>

</main>

<script defer src=" {{ mix('/js/client_show.min.js') }}"></script>

@endsection