@extends('accounts.nav')

@section('content')	
	@parent

	@if (!Auth::user()->account->isPro())
  <div class="container">
    <div class="row">
			<div style="font-size:larger;" class="col-md-8 col-md-offset-2">{{ trans('texts.pro_plan_custom_fields', ['link'=>'<a href="#" onclick="showProPlan()">'.trans('texts.pro_plan.remove_logo_link').'</a>']) }}</div>
			&nbsp;<p/>&nbsp;
		</div>		
	</div>
	@endif


	{{ Former::open()->addClass('col-md-8 col-md-offset-2 warn-on-exit') }}
	{{ Former::populate($account) }}

	{{ Former::legend('company_fields') }}
	{{ Former::text('custom_label1')->label(trans('texts.field_label')) }}
	{{ Former::text('custom_value1')->label(trans('texts.field_value')) }}
	<p>&nbsp;</p>
	{{ Former::text('custom_label2')->label(trans('texts.field_label')) }}
	{{ Former::text('custom_value2')->label(trans('texts.field_value')) }}

	{{ Former::legend('client_fields') }}
	{{ Former::text('custom_client_label1')->label(trans('texts.field_label')) }}
	{{ Former::text('custom_client_label2')->label(trans('texts.field_label')) }}

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


@stop