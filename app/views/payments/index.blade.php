@extends('header')

@section('content')
	
	{{ Datatable::table()		
    	->addColumn('Client', 'Invoice', 'Amount', 'Date')       
    	->setUrl(route('api.payments'))    	
    	->setOptions('sPaginationType', 'bootstrap')
    	->setOptions('bFilter', false)
    	->render() }}
    	
@stop