@extends('accounts.nav')

@section('content')	
	@parent	

	{{ Former::open()->addClass('col-md-8 col-md-offset-2') }}	
	{{ Former::populate($account) }}
	{{ Former::populateField('notify_sent', intval(Auth::user()->notify_sent)) }}
	{{ Former::populateField('notify_viewed', intval(Auth::user()->notify_viewed)) }}
	{{ Former::populateField('notify_paid', intval(Auth::user()->notify_paid)) }}

	{{ Former::legend('Email Notifications') }}
	{{ Former::checkbox('notify_sent')->label('&nbsp;')->text('Email me when an invoice is <b>sent</b>') }}
	{{ Former::checkbox('notify_viewed')->label('&nbsp;')->text('Email me when an invoice is <b>viewed</b>') }}
	{{ Former::checkbox('notify_paid')->label('&nbsp;')->text('Email me when an invoice is <b>paid</b>') }}

	{{ Former::legend('Custom messages') }}
	{{ Former::textarea('invoice_terms') }}
	{{ Former::textarea('email_footer') }}

	{{ Former::actions( Button::lg_primary_submit('Save') ) }}
	{{ Former::close() }}

@stop