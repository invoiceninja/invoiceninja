@extends('header')

@section('content')

	{{ Button::primary_link(URL::to('clients/create'), 'New Client', array('class' => 'pull-right')) }}	

	{{ Datatable::table()		
    	->addColumn('Client', 'Contact', 'Last Login', 'Email', 'Phone')       
    	->setUrl(route('api.clients'))    	
    	->setOptions('sPaginationType', 'bootstrap')
    	->setOptions('bFilter', false)
    	->render() }}
    	
@stop