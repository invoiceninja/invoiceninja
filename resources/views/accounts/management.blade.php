@extends('header')

@section('content')	
@parent

@include('accounts.nav', ['selected' => ACCOUNT_MANAGEMENT])

<div class="row">
	<div class="col-md-12">
		<!--<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">{!! trans('texts.plan_status') !!}</h3>
			</div>
			<div class="panel-body">
				<form class="form-horizontal">
					<div class="form-group">
						<label class="col-sm-2 control-label">Plan</label>
						<div class="col-sm-10">
							<p class="form-control-static">{{ trans('texts.plan_'.$account->plan) }}</p>
						</div>
					</div>
				</form>
			</div>
		</div>-->

		{!! Former::open('settings/cancel_account')->addClass('cancel-account') !!}
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">{!! trans('texts.cancel_account') !!}</h3>
			</div>
			<div class="panel-body">
				{!! Former::actions( Button::danger(trans('texts.cancel_account'))->large()->withAttributes(['onclick' => 'showConfirm()'])->appendIcon(Icon::create('trash'))) !!}
			</div>
		</div>

		<div class="modal fade" id="confirmCancelModal" tabindex="-1" role="dialog" aria-labelledby="confirmCancelModalLabel" aria-hidden="true">
			<div class="modal-dialog" style="min-width:150px">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
						<h4 class="modal-title" id="confirmCancelModalLabel">{!! trans('texts.cancel_account') !!}</h4>
					</div>

					<div style="background-color: #fff; padding-left: 16px; padding-right: 16px">
						&nbsp;<p>{{ trans('texts.cancel_account_message') }}</p>&nbsp;
						&nbsp;<p>{!! Former::textarea('reason')->placeholder(trans('texts.reason_for_canceling'))->raw() !!}</p>&nbsp;        
					</div>

					<div class="modal-footer" style="margin-top: 0px">
						<button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.go_back') }}</button>
						<button type="button" class="btn btn-danger" onclick="confirmCancel()">{{ trans('texts.cancel_account') }}</button>         
					</div>

				</div>
			</div>
		</div>
		{!! Former::close() !!}  
	</div>
</div>

<script type="text/javascript">
  function showConfirm() {
    $('#confirmCancelModal').modal('show'); 
  }

  function confirmCancel() {
    $('form.cancel-account').submit();
  }
</script>
@stop