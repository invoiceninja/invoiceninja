@extends('header')

@section('content')
@parent

@include('accounts.nav', ['selected' => ACCOUNT_MANAGEMENT])

<div class="row">
	<div class="col-md-12">
		{!! Former::open('settings/change_plan')->addClass('change-plan') !!}
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">{!! trans('texts.plan_status') !!}</h3>
			</div>
			<div class="panel-body">
				<div class="form-group">
					<label class="col-sm-4 control-label">{{ trans('texts.plan') }}</label>
					<div class="col-sm-8">
						<p class="form-control-static">
							@if ($planDetails && $planDetails['active'])
								{{ trans('texts.plan_'.$planDetails['plan']) }}
								@if ($planDetails['trial'])
									({{ trans('texts.plan_trial') }})
								@elseif ($planDetails['expires'])
									({{ trans('texts.plan_term_'.$planDetails['term'].'ly') }})
								@endif
							@elseif(Utils::isNinjaProd())
								{{ trans('texts.plan_free') }}
							@else
								{{ trans('texts.plan_free_self_hosted') }}
							@endif
						</p>
					</div>
				</div>
				@if ($planDetails && $planDetails['active'])
					<div class="form-group">
						<label class="col-sm-4 control-label">
							@if((!$account->company->pending_plan || $account->company->pending_plan == $planDetails['plan']) && $planDetails['expires'] && !$planDetails['trial'])
								{{ trans('texts.renews') }}
							@else
								{{ trans('texts.expires') }}
							@endif
						</label>
						<div class="col-sm-8">
							<p class="form-control-static">
								@if ($planDetails['expires'] === false)
									{{ trans('texts.never') }}
								@else
									{{ Utils::dateToString($planDetails['expires']) }}
								@endif
							</p>
						</div>
					</div>
					@if ($account->company->pending_plan)
					<div class="form-group">
						<label class="col-sm-4 control-label">{{ trans('texts.pending_change_to') }}</label>
						<div class="col-sm-8">
							<p class="form-control-static">
								@if ($account->company->pending_plan == PLAN_FREE)
									{{ trans('texts.plan_changes_to', [
										'plan'=>trans('texts.plan_free'),
										'date'=>Utils::dateToString($planDetails['expires'])
									]) }}
								@else
									{{ trans('texts.plan_term_changes_to', [
										'plan'=>trans('texts.plan_'.$account->company->pending_plan),
										'term'=>trans('texts.plan_term_'.$account->company->pending_term.'ly'),
										'date'=>Utils::dateToString($planDetails['expires'])
									]) }}
								@endif<br>
								<a href="#" onclick="cancelPendingChange()">{{ trans('texts.cancel_plan_change') }}</a>
							</p>
						</div>
					</div>
					@endif
					@if (Utils::isNinjaProd())
						{!! Former::actions( Button::info(trans('texts.plan_change'))->large()->withAttributes(['onclick' => 'showChangePlan()'])->appendIcon(Icon::create('edit'))) !!}
					@endif
				@else
					@if ($planDetails)
						<div class="form-group">
							<label class="col-sm-4 control-label">
								@if ($planDetails['trial'])
									{{ trans('texts.trial_expired', ['plan'=>trans('texts.plan_'.$planDetails['plan'])]) }}
								@else
									{{ trans('texts.plan_expired', ['plan'=>trans('texts.plan_'.$planDetails['plan'])]) }}
								@endif
							</label>
							<div class="col-sm-8">
								<p class="form-control-static">
									{{ Utils::dateToString($planDetails['expires']) }}
								</p>
							</div>
						</div>
					@endif
					@if (Utils::isNinjaProd())
					   {!! Former::actions( Button::success(trans('texts.plan_upgrade'))->large()->withAttributes(['onclick' => 'showChangePlan()'])->appendIcon(Icon::create('plus-sign'))) !!}
					@elseif (!$account->hasFeature(FEATURE_WHITE_LABEL))
					   {!! Former::actions( Button::success(trans('texts.white_label_button'))->large()->withAttributes(['onclick' => 'loadImages("#whiteLabelModal");$("#whiteLabelModal").modal("show");'])->appendIcon(Icon::create('plus-sign'))) !!}
					@endif
				@endif
			</div>
		</div>
		@if (Utils::isNinjaProd())
			<div class="modal fade" id="changePlanModel" tabindex="-1" role="dialog" aria-labelledby="changePlanModelLabel" aria-hidden="true">
				<div class="modal-dialog" style="min-width:150px">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
							<h4 class="modal-title" id="changePlanModelLabel">
								@if ($planDetails && $planDetails['active'])
									{!! trans('texts.plan_change') !!}
								@else
									{!! trans('texts.plan_upgrade') !!}
								@endif
							</h4>
						</div>
						<div class="modal-body">
							@if ($planDetails && $planDetails['active'])
							{!! Former::select('plan')
								->addOption(trans('texts.plan_enterprise'), PLAN_ENTERPRISE)
								->addOption(trans('texts.plan_pro'), PLAN_PRO)
								->addOption(trans('texts.plan_free'), PLAN_FREE)!!}
							@else
							{!! Former::select('plan')
								->addOption(trans('texts.plan_enterprise'), PLAN_ENTERPRISE)
								->addOption(trans('texts.plan_pro'), PLAN_PRO)!!}
							@endif
							{!! Former::select('plan_term')
								->addOption(trans('texts.plan_term_yearly'), PLAN_TERM_YEARLY)
								->addOption(trans('texts.plan_term_monthly'), PLAN_TERM_MONTHLY)
                                ->inlineHelp(trans('texts.enterprise_plan_features', ['link' => link_to(NINJA_WEB_URL . '/plans-pricing', trans('texts.click_here'), ['target' => '_blank'])])) !!}
						</div>
						<div class="modal-footer" style="margin-top: 0px">
							<button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.go_back') }}</button>
							@if ($planDetails && $planDetails['active'])
								<button type="button" class="btn btn-primary" onclick="confirmChangePlan()">{{ trans('texts.plan_change') }}</button>
							@else
								<button type="button" class="btn btn-success" onclick="confirmChangePlan()">{{ trans('texts.plan_upgrade') }}</button>
							@endif
						</div>
					</div>
				</div>
			</div>
		@endif
		{!! Former::close() !!}

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
	function showChangePlan() {
		$('#changePlanModel').modal('show');
	}

	function confirmChangePlan() {
		$('form.change-plan').submit();
	}

	function showConfirm() {
		$('#confirmCancelModal').modal('show');
	}

	function confirmCancel() {
		$('form.cancel-account').submit();
	}

	@if ($account->company->pending_plan)
	function cancelPendingChange(){
		$('#plan').val('{{ $planDetails['plan'] }}')
		$('#plan_term').val('{{ $planDetails['term'] }}')
		confirmChangePlan();
		return false;
	}
	@endif

  	jQuery(document).ready(function($){
		function updatePlanModal() {
			var plan = $('#plan').val();
	 		$('#plan_term').closest('.form-group').toggle(plan!='free');

			if(plan=='{{PLAN_PRO}}'){
				$('#plan_term option[value=month]').text({!! json_encode(trans('texts.plan_price_monthly', ['price'=>PLAN_PRICE_PRO_MONTHLY])) !!});
				$('#plan_term option[value=year]').text({!! json_encode(trans('texts.plan_price_yearly', ['price'=>PLAN_PRICE_PRO_YEARLY])) !!});
			} else if(plan=='{{PLAN_ENTERPRISE}}') {
				$('#plan_term option[value=month]').text({!! json_encode(trans('texts.plan_price_monthly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY])) !!});
				$('#plan_term option[value=year]').text({!! json_encode(trans('texts.plan_price_yearly', ['price'=>PLAN_PRICE_ENTERPRISE_YEARLY])) !!});
			}
  	  	}
		$('#plan_term, #plan').change(updatePlanModal);
	  	updatePlanModal();

		if(window.location.hash) {
			var hash = window.location.hash;
			$(hash).modal('toggle');
	  	}

        @if (Request::input('upgrade'))
          showChangePlan();
        @endif
  	});
</script>
@stop
