@extends('header')

@section('content')

	<h3>View Invoice</h3>

	{{ $invoice->number }} - {{ $invoice->client->name }}

@stop