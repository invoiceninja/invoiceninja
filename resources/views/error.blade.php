@extends('public.header')

@section('content')

<p>&nbsp;<p>
<p>&nbsp;<p>

<div class="well">
  <div class="container" style="min-height:400px">
  <h3>{{ trans('texts.error_title') }}...</h3>
  <h4>{{ $error }}</h4>
  <h4>{{ trans('texts.error_contact_text', ['mailaddress' => env('CONTACT_EMAIL', env('MAIL_FROM_ADDRESS'))]) }}</h4>
</div>
</div>

<p>&nbsp;<p>
<p>&nbsp;<p>

@stop
