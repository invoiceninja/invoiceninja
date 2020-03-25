@extends('email.template.master', ['design' => 'light'])
@section('title')
	@if(isset($title))
		{{ $title }}
	@endif
@endsection
@section('content')
	@if(isset($body))
		{!! $body !!}
	@endif
@endsection
@section('footer')
	@if(isset($footer))
		{!! $footer !!}
	@endif
@endsection