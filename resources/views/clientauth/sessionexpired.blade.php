@extends('login')
@section('form')
    <div class="form-signin">
        <h2 class="form-signin-heading">{{ trans('texts.session_expired') }}</h2>
        <hr class="green">
        <h4><center>{{ trans('texts.client_session_expired_message') }}</center></h4>
    </div>
@endsection