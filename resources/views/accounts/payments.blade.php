@extends('header')

@section('content')
	@parent
    @include('accounts.nav', ['selected' => ACCOUNT_PAYMENTS])
	@include('money_script')

    {!! Former::open()->addClass('warn-on-exit') !!}
    {!! Former::populateField('token_billing_type_id', $account->token_billing_type_id) !!}
    {!! Former::populateField('auto_bill_on_due_date', $account->auto_bill_on_due_date) !!}
	{!! Former::populateField('gateway_fee_enabled', $account->gateway_fee_enabled) !!}

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.payment_settings') !!}</h3>
        </div>
        <div class="panel-body">
            {!! Former::select('token_billing_type_id')
                        ->options($tokenBillingOptions)
                        ->help(trans('texts.token_billing_help')) !!}

            {!! Former::inline_radios('auto_bill_on_due_date')
                        ->label(trans('texts.auto_bill'))
                        ->radios([
                            trans('texts.on_send_date') => ['value'=>0, 'name'=>'auto_bill_on_due_date'],
                            trans('texts.on_due_date') => ['value'=>1, 'name'=>'auto_bill_on_due_date'],
                        ])->help(trans('texts.auto_bill_ach_date_help')) !!}

			{!! Former::checkbox('gateway_fee_enabled')
						->help('gateway_fees_help')
						->label('gateway_fees')
						->text('enable')
			 			->value(1) !!}
			<br/>
            {!! Former::actions( Button::success(trans('texts.save'))->withAttributes(['id' => 'formSave'])->submit()->appendIcon(Icon::create('floppy-disk')) ) !!}
        </div>
    </div>

    {!! Former::close() !!}

  @if ($showAdd)
      {!! Button::primary(trans('texts.add_gateway'))
            ->asLinkTo(URL::to('/gateways/create'))
            ->withAttributes(['class' => 'pull-right'])
            ->appendIcon(Icon::create('plus-sign')) !!}
  @endif

  @include('partials.bulk_form', ['entityType' => ENTITY_ACCOUNT_GATEWAY])

  {!! Datatable::table()
      ->addColumn(
        trans('texts.gateway'),
        trans('texts.limits'),
		trans('texts.fees'),
        trans('texts.action'))
      ->setUrl(url('api/gateways/'))
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)
      ->setOptions('bAutoWidth', false)
      ->setOptions('aoColumns', [[ "sWidth"=> "20%" ], ["sWidth"=> "20%"], ["sWidth"=> "30%"], ["sWidth"=> "20%"]])
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[1, 2, 3]]])
      ->render('datatable') !!}

    {!! Former::open( 'settings/payment_gateway_limits') !!}

    <div class="modal fade" id="paymentLimitsModal" tabindex="-1" role="dialog"
         aria-labelledby="paymentLimitsModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" style="min-width:150px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="paymentLimitsModalLabel"></h4>
                </div>

				<div class="container" style="width: 100%; padding-bottom: 2px !important">
	            <div class="panel panel-default">
	            <div class="panel-body">
					<div role="tabpanel">
		                <ul class="nav nav-tabs" role="tablist" style="border: none">
		                    <li role="presentation" class="active">
		                        <a href="#limits" aria-controls="limits" role="tab" data-toggle="tab">{{ trans('texts.limits') }}</a>
		                    </li>
		                    <li role="presentation">
		                        <a href="#fees" aria-controls="fees" role="tab" data-toggle="tab">{{ trans('texts.fees') }}</a>
		                    </li>
		                </ul>
		            </div>
		            <div class="tab-content">
		                <div role="tabpanel" class="tab-pane active" id="limits">
		                    <div class="panel-body"><br/>
								<div class="row" style="text-align:center">
			                        <div class="col-xs-12">
			                            <div id="payment-limits-slider"></div>
			                        </div>
			                    </div><br/>
			                    <div class="row">
			                        <div class="col-md-6">
			                            <div id="payment-limit-min-container">
			                                <label for="payment-limit-min">{{ trans('texts.min') }}</label><br>
			                                <div class="input-group" style="padding-bottom:8px">
			                                    <span class="input-group-addon">{{ $currency->symbol }}</span>
			                                    <input type="number" class="form-control" min="0" id="payment-limit-min"
			                                           name="limit_min">
			                                </div>
			                                <label><input type="checkbox" id="payment-limit-min-enable"
			                                              name="limit_min_enable"> {{ trans('texts.enable_min') }}</label>
			                            </div>
			                        </div>
			                        <div class="col-md-6">
			                            <div id="payment-limit-max-container">
			                                <label for="payment-limit-max">{{ trans('texts.max') }}</label><br>

			                                <div class="input-group" style="padding-bottom:8px">
			                                    <span class="input-group-addon">{{ $currency->symbol }}</span>
			                                    <input type="number" class="form-control" min="0" id="payment-limit-max"
			                                           name="limit_max">
			                                </div>
			                                <label><input type="checkbox" id="payment-limit-max-enable"
			                                              name="limit_max_enable"> {{ trans('texts.enable_max') }}</label>
			                            </div>
			                        </div>
			                    </div>

		                    </div>
		                </div>
						<div role="tabpanel" class="tab-pane" id="fees">
							<div id="feesDisabled" class="panel-body" style="display:none">
								<div class="help-block">
									{{ trans('texts.fees_disabled_for_gateway') }}
								</div>
							</div>
		                    <div id="feesEnabled" class="panel-body">

								{!! Former::text('fee_amount')
										->label('amount')
										->onchange('updateFeeSample()')
										->type('number')
										->step('any') !!}

								{!! Former::text('fee_percent')
										->label('percent')
										->onchange('updateFeeSample()')
										->type('number')
										->step('any')
										->append('%') !!}

								@if ($account->invoice_item_taxes)
							        {!! Former::select('tax_rate1')
										  ->onchange('onTaxRateChange(1)')
							              ->addOption('', '')
							              ->label(trans('texts.tax_rate'))
							              ->fromQuery($taxRates, function($model) { return $model->name . ': ' . $model->rate . '%'; }, 'public_id') !!}

									@if ($account->enable_second_tax_rate)
									{!! Former::select('tax_rate2')
										  ->onchange('onTaxRateChange(2)')
							              ->addOption('', '')
							              ->label(trans('texts.tax_rate'))
							              ->fromQuery($taxRates, function($model) { return $model->name . ': ' . $model->rate . '%'; }, 'public_id') !!}
									@endif

								@endif

								<div style="display:none">
									{!! Former::text('fee_tax_name1') !!}
									{!! Former::text('fee_tax_rate1') !!}
									{!! Former::text('fee_tax_name2') !!}
									{!! Former::text('fee_tax_rate2') !!}
								</div><br/>

								<div class="help-block">
									<span id="feeSample"></span>
									@if ($account->gateway_fee_enabled && !$account->invoice_item_taxes && $account->invoice_taxes && count($taxRates))
										<br/>{{ trans('texts.fees_tax_help') }}
								    @endif
								</div>

								<br/><b>{{ trans('texts.gateway_fees_disclaimer') }}</b>

		                    </div>
		                </div>
					</div>

                    <input type="hidden" name="gateway_type_id" id="payment-limit-gateway-type">
                </div>
                </div>
				</div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default"
                            data-dismiss="modal">{{ trans('texts.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="modalSave">{{ trans('texts.save') }}</button>
                </div>
            </div>
        </div>
    </div>
    {!! Former::close() !!}

  <script>
    window.onDatatableReady = actionListHandler;

	var taxRates = {!! $taxRates !!};
	var taxRatesMap = {};
	for (var i=0; i<taxRates.length; i++) {
		var taxRate = taxRates[i];
		taxRatesMap[taxRate.public_id] = taxRate;
	}
	var gatewaySettings = {};

	@foreach ($account->account_gateway_settings as $setting)
		gatewaySettings[{{ $setting->gateway_type_id }}] = {!! $setting !!};
	@endforeach

    //function showLimitsModal(gateway_type, gateway_type_id, min_limit, max_limit, fee_amount, fee_percent, fee_tax_name1, fee_tax_rate1, fee_tax_name2, fee_tax_rate2) {
	function showLimitsModal(gateway_type, gateway_type_id) {
		var settings = gatewaySettings[gateway_type_id];
        var modalLabel = {!! json_encode(trans('texts.set_limits_fees')) !!};
        $('#paymentLimitsModalLabel').text(modalLabel.replace(':gateway_type', gateway_type));

		var min_limit = settings ? settings.min_limit : null;
		var max_limit = settings ? settings.max_limit : null

		limitsSlider.noUiSlider.set([min_limit !== null ? min_limit : 0, max_limit !== null ? max_limit : 100000]);

        if (min_limit !== null) {
            $('#payment-limit-min').removeAttr('disabled');
            $('#payment-limit-min-enable').prop('checked', true);
        } else {
            $('#payment-limit-min').attr('disabled', 'disabled');
            $('#payment-limit-min-enable').prop('checked', false);
        }

        if (max_limit !== null) {
            $('#payment-limit-max').removeAttr('disabled');
            $('#payment-limit-max-enable').prop('checked', true);
        } else {
            $('#payment-limit-max').attr('disabled', 'disabled');
            $('#payment-limit-max-enable').prop('checked', false);
        }

        $('#payment-limit-gateway-type').val(gateway_type_id);

		if (settings) {
			$('#fee_amount').val(settings.fee_amount);
			$('#fee_percent').val(settings.fee_percent);
			setTaxRate(1, settings.fee_tax_name1, settings.fee_tax_rate1);
			setTaxRate(2, settings.fee_tax_name2, settings.fee_tax_rate2);
		} else {
			$('#fee_amount').val('');
			$('#fee_percent').val('');
			setTaxRate(1, '', '');
			setTaxRate(2, '', '');
		}

		updateFeeSample();

		if (gateway_type_id == {{ GATEWAY_TYPE_CUSTOM }}) {
			$('#feesEnabled').hide();
			$('#feesDisabled').show();
		} else {
			$('#feesDisabled').hide();
			$('#feesEnabled').show();
		}

		$('#paymentLimitsModal').modal('show');
    }

    var limitsSlider = document.getElementById('payment-limits-slider');
    noUiSlider.create(limitsSlider, {
        start: [0, 100000],
        connect: true,
        range: {
            'min': [0, 1],
            '30%': [500, 1],
            '70%': [5000, 1],
            'max': [100000, 1]
        }
    });

    limitsSlider.noUiSlider.on('update', function (values, handle) {
        var value = Math.round(values[handle]);
        if (handle == 1) {
            $('#payment-limit-max').val(value).removeAttr('disabled');
            $('#payment-limit-max-enable').prop('checked', true);
        } else {
            $('#payment-limit-min').val(value).removeAttr('disabled');
            $('#payment-limit-min-enable').prop('checked', true);
        }
    });

    $('#payment-limit-min').on('change input', function () {
        setTimeout(function () {
            limitsSlider.noUiSlider.set([$('#payment-limit-min').val(), null]);
        }, 100);
        $('#payment-limit-min-enable').attr('checked', 'checked');
    });

    $('#payment-limit-max').on('change input', function () {
        setTimeout(function () {
            limitsSlider.noUiSlider.set([null, $('#payment-limit-max').val()]);
        }, 100);
        $('#payment-limit-max-enable').attr('checked', 'checked');
    });

    $('#payment-limit-min-enable').change(function () {
        if ($(this).is(':checked')) {
            $('#payment-limit-min').removeAttr('disabled');
        } else {
            $('#payment-limit-min').attr('disabled', 'disabled');
        }
    });

    $('#payment-limit-max-enable').change(function () {
        if ($(this).is(':checked')) {
            $('#payment-limit-max').removeAttr('disabled');
        } else {
            $('#payment-limit-max').attr('disabled', 'disabled');
        }
    });

	function updateFeeSample() {
		var feeAmount = NINJA.parseFloat($('#fee_amount').val()) || 0;
		var feePercent = NINJA.parseFloat($('#fee_percent').val()) || 0;
		var total = feeAmount + (feePercent * 100 / 100);
		var subtotal = total;

		var taxRate1 = $('#tax_rate1').val();
		if (taxRate1) {
			taxRate1 = NINJA.parseFloat(taxRatesMap[taxRate1].rate);
			total += subtotal * taxRate1 / 100;
		}

		var taxRate2 = NINJA.parseFloat($('#tax_rate2').val());
		if (taxRate2) {
			taxRate2 = NINJA.parseFloat(taxRatesMap[taxRate2].rate);
			total += subtotal * taxRate2 / 100;
		}

		if (total >= 0) {
			var str = "{{ trans('texts.fees_sample') }}";
		} else {
			var str = "{{ trans('texts.discount_sample') }}";
		}
		str = str.replace(':amount', formatMoney(100));
		str = str.replace(':total', formatMoney(total));
		$('#feeSample').text(str);
	}

	function onTaxRateChange(instance) {
		var taxRate = $('#tax_rate' + instance).val();
		if (taxRate) {
			taxRate = taxRatesMap[taxRate];
		}

		$('#fee_tax_name' + instance).val(taxRate ? taxRate.name : '');
		$('#fee_tax_rate' + instance).val(taxRate ? taxRate.rate : '');

		updateFeeSample();
	}

	function setTaxRate(instance, name, rate) {
		if (!name || !rate) {
			return;
		}
		var found = false;
		for (var i=0; i<taxRates.length; i++) {
			var taxRate = taxRates[i];
			if (taxRate.name == name && taxRate.rate == rate) {
				$('#tax_rate' + instance).val(taxRate.public_id);
				found = true;
			}
		}
		if (!found) {
			taxRatesMap[0] = {name:name, rate:rate, public_id:0};
			$('#tax_rate' + instance).append(new Option(name + ' ' + rate + '%', 0)).val(0);
		}

		onTaxRateChange(instance);
	}

  </script>

@stop
