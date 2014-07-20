@extends('accounts.nav')

@section('content')	
	@parent
	@include('accounts.nav_advanced')

	{{ Former::open()->addClass('col-md-8 col-md-offset-2 warn-on-exit') }}
	{{ Former::populate($account) }}
	{{ Former::populateField('hide_quantity', intval($account->hide_quantity)) }}
	{{ Former::populateField('hide_paid_to_date', intval($account->hide_paid_to_date)) }}

	{{ Former::legend('invoice_options') }}
	{{ Former::checkbox('hide_quantity')->text(trans('texts.hide_quantity_help')) }}
	{{ Former::checkbox('hide_paid_to_date')->text(trans('texts.hide_paid_to_date_help')) }}
	<p>&nbsp;</p>

	{{ Former::legend('invoice_design') }}
	{{ Former::text('primary_color') }}
	{{ Former::text('secondary_color') }}

	@if (Auth::user()->isPro())
	{{ Former::actions( Button::lg_success_submit(trans('texts.save'))->append_with_icon('floppy-disk') ) }}
	@else
	<script>
	    $(function() {   
	    	$('form.warn-on-exit input').prop('disabled', true);
	    });
	</script>	
	@endif

	{{ Former::close() }}

	<script>
	    $(function() {   
	    	var options = {
	    		preferredFormat: "hex",
	    		disabled: {{ Auth::user()->isPro() ? 'false' : 'true' }},
	    		showInitial: false,
	    		showInput: true,
	    		allowEmpty: true,
	    		clickoutFiresChange: true,
	    	};
	    	$('#primary_color').spectrum(options);
	    	$('#secondary_color').spectrum(options);
	    });
	</script>	


@stop