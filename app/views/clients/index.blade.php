@extends('header')

@section('content')

	{{ Button::primary_link(URL::to('clients/create'), 'New Client', array('class' => 'pull-right')) }}	

	{{ Datatable::table()		
    	->addColumn('Client', 'Contact', 'Balance', 'Last Login', 'Date Created', 'Email', 'Phone')       
    	->setUrl(route('api.clients'))    	
    	->setOptions('sPaginationType', 'bootstrap')
    	->setOptions('bFilter', false)
    	->render() }}
    	
@stop