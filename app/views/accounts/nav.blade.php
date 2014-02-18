@extends('header')

@section('content')
		
	<ul class="nav nav-tabs nav nav-justified">
		{{ HTML::nav_link('company/details', 'Company Details') }}
		{{ HTML::nav_link('company/payments', 'Online Payments') }}
    {{ HTML::nav_link('company/notifications', 'Notifications') }}
		{{ HTML::nav_link('company/import_export', 'Import/Export', 'company/import_map') }}
	</ul>
	<p>&nbsp;</p>

@stop