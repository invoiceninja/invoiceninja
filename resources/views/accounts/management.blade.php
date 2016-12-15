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
                                @if ($planDetails['plan'] == PLAN_ENTERPRISE)
                                    {{ trans('texts.min_to_max_users', ['min' => Utils::getMinNumUsers($planDetails['num_users']), 'max' => $planDetails['num_users']])}}
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
							{{ trans('texts.renews') }}
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

					@if ($account->company->hasActiveDiscount())
						{!! Former::plaintext('discount')
								->value($account->company->present()->discountMessage) !!}
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
                                    ->onchange('onPlanChange()')
                                    ->addOption(trans('texts.plan_free'), PLAN_FREE)
    								->addOption(trans('texts.plan_pro'), PLAN_PRO)
                                    ->addOption(trans('texts.plan_enterprise'), PLAN_ENTERPRISE) !!}
							@else
    							{!! Former::select('plan')
                                    ->onchange('onPlanChange()')
                                    ->addOption(trans('texts.plan_pro'), PLAN_PRO)
    								->addOption(trans('texts.plan_enterprise'), PLAN_ENTERPRISE) !!}
							@endif

                            <div id="numUsersDiv">
                                {!! Former::select('num_users')
                                    ->label(trans('texts.users'))
                                    ->addOption('1 to 2', 2)
    								->addOption('3 to 5', 5)
                                    ->addOption('6 to 10', 10) !!}
                            </div>

							{!! Former::select('plan_term')
								->addOption(trans('texts.plan_term_monthly'), PLAN_TERM_MONTHLY)
                                ->addOption(trans('texts.plan_term_yearly'), PLAN_TERM_YEARLY)
								->inlineHelp(trans('texts.enterprise_plan_features', ['link' => link_to(NINJA_WEB_URL . '/plans-pricing', trans('texts.click_here'), ['target' => '_blank'])])) !!}

							{!! Former::plaintext(' ')
								->inlineHelp($account->company->present()->promoMessage) !!}

						</div>
						<div class="modal-footer" style="margin-top: 0px">
                            @if (Utils::isPro())
                                <div class="pull-left" style="padding-top: 8px;color:#888888">
                                    {{ trans('texts.changes_take_effect_immediately') }}
                                </div>
                            @endif
							<button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.go_back') }}</button>
							@if ($planDetails && $planDetails['active'])
								<button type="button" class="btn btn-primary" id="changePlanButton" onclick="confirmChangePlan()">{{ trans('texts.plan_change') }}</button>
							@else
								<button type="button" class="btn btn-success" id="changePlanButton" onclick="confirmChangePlan()">{{ trans('texts.plan_upgrade') }}</button>
							@endif
						</div>
					</div>
				</div>
			</div>
		@endif
		{!! Former::close() !!}


		{!! Former::open('settings/account_management') !!}
		{!! Former::populateField('live_preview', intval($account->live_preview)) !!}
		{!! Former::populateField('force_pdfjs', intval(Auth::user()->force_pdfjs)) !!}

		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">{!! trans('texts.modules') !!}</h3>
			</div>
			<div class="panel-body">
				<div class="form-group">
					<label for="modules" class="control-label col-lg-4 col-sm-4"></label>
					<div class="col-lg-8 col-sm-8">
						@foreach (\App\Models\Account::$modules as $entityType => $value)
						<div class="checkbox">
							<label for="modules_{{ $value}}">
								<input name="modules[]" id="modules_{{ $value}}" type="checkbox" {{ Auth::user()->account->isModuleEnabled($entityType) ? 'checked="checked"' : '' }} value="{{ $value }}">{{ trans("texts.{$entityType}s") }}
							</label>
						</div>
						@endforeach
					</div>
				</div>
				<div class="form-group">
					<label for="modules" class="control-label col-lg-4 col-sm-4"></label>
					<div class="col-lg-8 col-sm-8">
						{!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
					</div>
				</div>
			</div>
		</div>

		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">{!! trans('texts.pdf_settings') !!}</h3>
			</div>
			<div class="panel-body">

				{!! Former::checkbox('live_preview')
						->text(trans('texts.enable'))
						->help('live_preview_help') !!}

				{!! Former::checkbox('force_pdfjs')
						->text(trans('texts.enable'))
						->help(trans('texts.force_pdfjs_help', [
							'chrome_link' => link_to(CHROME_PDF_HELP_URL, 'Chrome', ['target' => '_blank']),
							'firefox_link' => link_to(FIREFOX_PDF_HELP_URL, 'Firefox', ['target' => '_blank']),
						])) !!}

				<div class="form-group">
					<label for="modules" class="control-label col-lg-4 col-sm-4"></label>
					<div class="col-lg-8 col-sm-8">
						{!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
					</div>
				</div>
			</div>
		</div>

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

	// show plan popupl when clicking 'Upgrade' in navbar
	function showUpgradeModal() {
		showChangePlan();
	}

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

    function onPlanChange() {
        if ($('#plan').val() == '{{ PLAN_ENTERPRISE }}') {
            $('#numUsersDiv').show();
        } else {
            $('#numUsersDiv').hide();
        }
    }

  	jQuery(document).ready(function($){
		function updatePlanModal() {
			var plan = $('#plan').val();
            var numUsers = $('#num_users').val();
	 		$('#plan_term').closest('.form-group').toggle(plan!='free');

			if(plan=='{{PLAN_PRO}}'){
				$('#plan_term option[value=month]').text({!! json_encode(trans('texts.plan_price_monthly', ['price'=>PLAN_PRICE_PRO_MONTHLY])) !!});
				$('#plan_term option[value=year]').text({!! json_encode(trans('texts.plan_price_yearly', ['price'=>PLAN_PRICE_PRO_MONTHLY * 10])) !!});
			} else if(plan=='{{PLAN_ENTERPRISE}}') {
                if (numUsers == 2) {
                    $('#plan_term option[value=month]').text({!! json_encode(trans('texts.plan_price_monthly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY_2])) !!});
                    $('#plan_term option[value=year]').text({!! json_encode(trans('texts.plan_price_yearly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY_2 * 10])) !!});
                } else if (numUsers == 5) {
                    $('#plan_term option[value=month]').text({!! json_encode(trans('texts.plan_price_monthly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY_5])) !!});
                    $('#plan_term option[value=year]').text({!! json_encode(trans('texts.plan_price_yearly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY_5 * 10])) !!});
                } else {
                    $('#plan_term option[value=month]').text({!! json_encode(trans('texts.plan_price_monthly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY_10])) !!});
                    $('#plan_term option[value=year]').text({!! json_encode(trans('texts.plan_price_yearly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY_10 * 10])) !!});
                }
			}
  	  	}
		$('#plan_term, #plan, #num_users').change(updatePlanModal);
	  	updatePlanModal();
        onPlanChange();

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
