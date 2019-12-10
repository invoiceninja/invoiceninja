@extends('email.template.master')
@section('title')
{{ $title }}
@endsection
@section('content')
{!! $body !!}
@endsection
@section('footer')
{!! $footer !!}
@endsection