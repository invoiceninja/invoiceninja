@extends('header')

@section('content')

	<ul class="nav nav-tabs nav nav-justified">
  	{!! HTML::nav_link('company/details', 'company_details') !!}
    {!! HTML::nav_link('company/payments', 'online_payments', 'gateways') !!}
    {!! HTML::nav_link('company/products', 'product_library') !!}
  	{!! HTML::nav_link('company/notifications', 'notifications') !!}
    {!! HTML::nav_link('company/import_export', 'import_export', 'company/import_map') !!}
  	{!! HTML::nav_link('company/advanced_settings/invoice_design', 'advanced_settings', '*/advanced_settings/*') !!}
	</ul>

    <br/>

@stop