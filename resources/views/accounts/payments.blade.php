@extends('header')

@section('content')	
	@parent	
    @include('accounts.nav', ['selected' => ACCOUNT_PAYMENTS])

  @if ($showSwitchToWepay)
      {!! Button::success(trans('texts.switch_to_wepay'))
            ->asLinkTo(URL::to('/gateways/switch/wepay'))
            ->appendIcon(Icon::create('circle-arrow-up')) !!}
      &nbsp;
  @endif
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