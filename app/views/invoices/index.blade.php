@extends('header')

@section('content')

	{{ Button::primary_link(URL::to('invoices/create'), 'New Invoice', array('class' => 'pull-right')) }}	
	
	{{ Datatable::table()		
    	->addColumn('Number', 'Client', 'Amount', 'Date')       
    	->setUrl(route('api.invoices'))    	
    	->setOptions('sPaginationType', 'bootstrap')
    	->setOptions('bFilter', false)
    	->render() }}

@stop