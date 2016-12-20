@extends('header')

@section('content')
	@parent
    @include('accounts.nav', ['selected' => ACCOUNT_PAYMENTS])

    {!! Former::open()->addClass('warn-on-exit') !!}
    {!! Former::populateField('token_billing_type_id', $account->token_billing_type_id) !!}
    {!! Former::populateField('auto_bill_on_due_date', $account->auto_bill_on_due_date) !!}


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
            <div class="form-group">
                <div class="col-sm-offset-4 col-sm-8"><p>{!! trans('texts.payment_settings_supported_gateways') !!}</p></div>
            </div>
            {!! Former::actions( Button::success(trans('texts.save'))->submit()->appendIcon(Icon::create('floppy-disk')) ) !!}
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
        trans('texts.name'),
        trans('texts.limit'),
        trans('texts.action'))
      ->setUrl(url('api/gateways/'))
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)
      ->setOptions('bAutoWidth', false)
      ->setOptions('aoColumns', [[ "sWidth"=> "50%" ], ["sWidth"=> "30%"], ["sWidth"=> "20%"]])
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[1]]])
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

                <div class="modal-body">
                    <div class="panel-body">
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
                        <input type="hidden" name="gateway_type_id" id="payment-limit-gateway-type">
                    </div>
                </div>

                <div class="modal-footer" style="margin-top: 0px">
                    <button type="button" class="btn btn-default"
                            data-dismiss="modal">{{ trans('texts.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ trans('texts.save') }}</button>
                </div>
            </div>
        </div>
    </div>
    {!! Former::close() !!}

  <script>
    window.onDatatableReady = actionListHandler;

    function showLimitsModal(gateway_type, gateway_type_id, min_limit, max_limit) {
        var modalLabel = {!! json_encode(trans('texts.set_limits')) !!};
        $('#paymentLimitsModalLabel').text(modalLabel.replace(':gateway_type', gateway_type));

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

  </script>



@stop
