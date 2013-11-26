@extends('header')

@section('content')

	<h3>View Client</h3>

	{{ $client->name }}
	
	<div class="pull-right">
		{{ Button::link(URL::to('clients/' . $client->id . '/edit'), 'Edit Client') }}
		{{ Button::primary_link(URL::to('invoices/create/' . $client->id), 'Create Invoice') }}
	</div>

@stop