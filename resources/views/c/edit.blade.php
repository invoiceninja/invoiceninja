@extends('layouts.master', ['header' => $header])

@section('body')
<main class="main" id="client_e">
    
    <!-- Breadcrumb-->
    {{ Breadcrumbs::render('clients.edit', $client) }}
    <vue-toastr ref="toastr"></vue-toastr>

    <client-edit-form :clientdata="{{ $client }}" :hashed_id="'{{ $hashed_id }}'"></client-edit-form>

</main>

<script defer src=" {{ mix('/js/client-edit.js') }}"></script>

@endsection