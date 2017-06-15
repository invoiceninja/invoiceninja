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
				@if (Auth::user()->primaryAccount()->id != Auth::user()->account->id)
					<center style="font-size:16px;color:#888888;">
						{{ trans('texts.switch_to_primary', ['name' => Auth::user()->primaryAccount()->getDisplayName()]) }}
					</center>
				@else
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
									@if ($portalLink)
										- {{ link_to($portalLink, trans('texts.view_client_portal'), ['target' => '_blank']) }}
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

						@if (Utils::isNinjaProd() && Auth::user()->confirmed)
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
							@if (Auth::user()->confirmed)
						   		{!! Former::actions( Button::success(trans('texts.plan_upgrade'))->large()->withAttributes(['onclick' => 'showChangePlan()'])->appendIcon(Icon::create('plus-sign'))) !!}
							@endif
						@elseif (!$account->hasFeature(FEATURE_WHITE_LABEL))
						   {!! Former::actions( Button::success(trans('texts.white_label_button'))->large()->withAttributes(['onclick' => 'loadImages("#whiteLabelModal");$("#whiteLabelModal").modal("show");'])->appendIcon(Icon::create('plus-sign'))) !!}
						@endif
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
						<div class="container" style="width: 100%; padding-bottom: 0px !important">
			            <div class="panel panel-default">
			            <div class="panel-body">

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
                                    ->addOption('6 to 10', 10)
									->addOption('11 to 20', 20) !!}
                            </div>

							{!! Former::select('plan_term')
								->addOption(trans('texts.plan_term_monthly'), PLAN_TERM_MONTHLY)
                                ->addOption(trans('texts.plan_term_yearly'), PLAN_TERM_YEARLY)
								->inlineHelp(trans('texts.enterprise_plan_features', ['link' => link_to(NINJA_WEB_URL . '/plans-pricing', trans('texts.click_here'), ['target' => '_blank'])])) !!}

							{!! Former::plaintext(' ')
								->inlineHelp($account->company->present()->promoMessage) !!}

						</div>
						</div>
						</div>
						<div class="modal-footer">
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
						->help(trans('texts.live_preview_help') . '<br/>' . trans('texts.recommend_on'))
						->value(1) !!}

				{!! Former::checkbox('force_pdfjs')
						->text(trans('texts.enable'))
						->value(1)
						->help(trans('texts.force_pdfjs_help', [
							'chrome_link' => link_to(CHROME_PDF_HELP_URL, 'Chrome', ['target' => '_blank']),
							'firefox_link' => link_to(FIREFOX_PDF_HELP_URL, 'Firefox', ['target' => '_blank']),
						])  . '<br/>' . trans('texts.recommend_off')) !!}

				<div class="form-group">
					<label for="modules" class="control-label col-lg-4 col-sm-4"></label>
					<div class="col-lg-8 col-sm-8">
						{!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
					</div>
				</div>
			</div>
		</div>

		{!! Former::close() !!}

		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">{!! trans('texts.delete_data') !!}</h3>
			</div>
			<div class="panel-body">
				{!! Former::open('settings/purge_data')->addClass('purge-data') !!}
				{!! Former::actions(
						Button::danger(trans('texts.purge_data'))
							->withAttributes(['onclick' => 'showPurgeConfirm()'])
							->appendIcon(Icon::create('trash'))
							->large()
						) !!}
				<div class="form-group">
					<div class="col-lg-8 col-sm-8 col-lg-offset-4 col-sm-offset-4">
						<span class="help-block">{{ trans('texts.purge_data_help')}}</span>
					</div>
				</div>
				<br/>
				<div class="modal fade" id="confirmPurgeModal" tabindex="-1" role="dialog" aria-labelledby="confirmPurgeModalLabel" aria-hidden="true">
					<div class="modal-dialog" style="min-width:150px">
						<div class="modal-content">
							<div class="modal-header">
								<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
								<h4 class="modal-title" id="confirmPurgeModalLabel">{!! trans('texts.purge_data') !!}</h4>
							</div>
							<div class="container" style="width: 100%; padding-bottom: 0px !important">
			                <div class="panel panel-default">
			                <div class="panel-body">
								<p><b>{{ trans('texts.purge_data_message') }}</b></p>
								<br/>
							</div>
							</div>
							</div>
							<div class="modal-footer" style="margin-top: 2px">
								<button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.go_back') }}</button>
								<button type="button" class="btn btn-danger" onclick="confirmPurge()">{{ trans('texts.purge_data') }}</button>
							</div>
						</div>
					</div>
				</div>
				{!! Former::close() !!}

				{!! Former::open('settings/cancel_account')->addClass('cancel-account') !!}
				{!! Former::actions( Button::danger(trans('texts.cancel_account'))->large()->withAttributes(['onclick' => 'showCancelConfirm()'])->appendIcon(Icon::create('trash'))) !!}
				<div class="form-group">
					<div class="col-lg-8 col-sm-8 col-lg-offset-4 col-sm-offset-4">
						<span class="help-block">{{ trans('texts.cancel_account_help')}}</span>
					</div>
				</div>
				<div class="modal fade" id="confirmCancelModal" tabindex="-1" role="dialog" aria-labelledby="confirmCancelModalLabel" aria-hidden="true">
					<div class="modal-dialog" style="min-width:150px">
						<div class="modal-content">
							<div class="modal-header">
								<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
								<h4 class="modal-title" id="confirmCancelModalLabel">{!! trans('texts.cancel_account') !!}</h4>
							</div>
							<div class="container" style="width: 100%; padding-bottom: 0px !important">
			                <div class="panel panel-default">
			                <div class="panel-body">
								<p><b>{{ trans('texts.cancel_account_message') }}</b></p><br/>
								<p>{!! Former::textarea('reason')
											->placeholder(trans('texts.reason_for_canceling'))
											->raw()
											->rows(4) !!}</p>
								<br/>
							</div>
							</div>
							</div>
							<div class="modal-footer" style="margin-top: 2px">
								<button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.go_back') }}</button>
								<button type="button" class="btn btn-danger" onclick="confirmCancel()">{{ trans('texts.cancel_account') }}</button>
							</div>
						</div>
					</div>
				</div>
				{!! Former::close() !!}
			</div>
		</div>

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

	function showCancelConfirm() {
		$('#confirmCancelModal').modal('show');
	}

	function showPurgeConfirm() {
		$('#confirmPurgeModal').modal('show');
	}

	function confirmCancel() {
		$('form.cancel-account').submit();
	}

	function confirmPurge() {
		$('form.purge-data').submit();
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
				} else if (numUsers == 10) {
                    $('#plan_term option[value=month]').text({!! json_encode(trans('texts.plan_price_monthly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY_10])) !!});
                    $('#plan_term option[value=year]').text({!! json_encode(trans('texts.plan_price_yearly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY_10 * 10])) !!});
				} else {
					$('#plan_term option[value=month]').text({!! json_encode(trans('texts.plan_price_monthly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY_20])) !!});
					$('#plan_term option[value=year]').text({!! json_encode(trans('texts.plan_price_yearly', ['price'=>PLAN_PRICE_ENTERPRISE_MONTHLY_20 * 10])) !!});
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
