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

  <label for="trashed" style="font-weight:normal; margin-left: 10px;">
    <input id="trashed" type="checkbox" onclick="setTrashVisible()"
      {{ Session::get("show_trash:gateway") ? 'checked' : ''}}/>&nbsp; {{ trans('texts.show_archived_deleted')}} {{ Utils::transFlowText('gateways') }}
  </label>

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
        trans('texts.payment_type_id'),
        trans('texts.action'))
      ->setUrl(url('api/gateways/'))
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)
      ->setOptions('bAutoWidth', false)
      ->setOptions('aoColumns', [[ "sWidth"=> "50%" ], [ "sWidth"=> "30%" ], ["sWidth"=> "20%"]])
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[2]]])
      ->render('datatable') !!}

  <script>
    window.onDatatableReady = actionListHandler;
    function setTrashVisible() {
        var checked = $('#trashed').is(':checked');
        var url = '{{ URL::to('view_archive/gateway') }}' + (checked ? '/true' : '/false');

        $.get(url, function(data) {
            refreshDatatable();
        })
    }
  </script>

@stop
