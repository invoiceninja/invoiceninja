@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.error_title'))

@section('body')
    <span class="text-center text-xl text-center">
        {{ ctrans('texts.payment_error_code', ['code' => isset($code) ? $code : '']) }}
    </span>

    @if($message)
	    <span class="mt-6 block">{{ ctrans('texts.message') }}:</span>
	    <ul>
	        <li>{{ $message }}.</li>
	    </ul>
    @endif
@endsection
