@extends('header')


@section('onReady')
	$('input#name').focus();
@stop

@section('content')

	
	{{ Former::open($url)->addClass('col-md-10 col-md-offset-1 main_form')->method($method)->rules(array(
  		'name' => 'required',
  		'email' => 'email'  		
	)); }}

	@if ($payment)
		{{ Former::populate($payment) }}
	@endif

	
	<div class="row">
		<div class="col-md-6">

			@if ($payment)
				{{ Former::legend('Edit Payment') }}
			@else
				{{ Former::legend('New Payment') }}
			@endif

			{{ Former::text('name') }}
			{{ Former::text('work_phone')->label('Phone') }}
			{{ Former::textarea('notes') }}

		</div>
		<div class="col-md-6">

		</div>
	</div>

	<center style="margin-top:16px">
		{{ Button::lg_primary_submit('Save') }} &nbsp;|&nbsp;
		{{ link_to('payments/' . ($payment ? $payment->public_id : ''), 'Cancel') }}	
	</center>

	{{ Former::close() }}

@stop