@extends('layouts.master', ['header' => $header])

@section('body')
<main class="main" id="client_e">
    
    <!-- Breadcrumb-->
    {{ Breadcrumbs::render('clients.edit', $client) }}

    <client-edit-form v-bind:clientdata="{{ $client }}"></client-edit-form>

</main>

<script defer src=" {{ mix('/js/client-edit.js') }}"></script>

@endsection