@extends('email.template.master', ['design' => 'dark'])
@section('title')
{{ $title }}
@endsection
@section('content')
{!! $body !!}
@endsection
@section('footer')
{!! $footer !!}
@endsection