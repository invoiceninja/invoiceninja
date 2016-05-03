@extends('header')

@section('content')
	@parent
	@include('accounts.nav', ['selected' => ACCOUNT_API_TOKENS, 'advanced' => true])

  <div class="pull-right">
  {!! Button::normal(trans('texts.documentation'))->asLinkTo(NINJA_WEB_URL.'/api-documentation/')->withAttributes(['target' => '_blank'])->appendIcon(Icon::create('info-sign')) !!}
  @if (Utils::isNinja() && !Utils::isReseller())  
    {!! Button::normal(trans('texts.zapier'))->asLinkTo(ZAPIER_URL)->withAttributes(['target' => '_blank']) !!}
  @endif
  @if (Utils::hasFeature(FEATURE_API))
    {!! Button::primary(trans('texts.add_token'))->asLinkTo(URL::to('/tokens/create'))->appendIcon(Icon::create('plus-sign')) !!}
  @endif
  </div>

  <!--
    <label for="trashed" style="font-weight:normal; margin-left: 10px;">
        <input id="trashed" type="checkbox" onclick="setTrashVisible()"
            {!! Session::get('show_trash:token') ? 'checked' : ''!!}/> {!! trans('texts.show_deleted_tokens')!!}
    </label>
  -->

  @include('partials.bulk_form', ['entityType' => ENTITY_TOKEN])

  {!! Datatable::table()
      ->addColumn(
        trans('texts.name'),
        trans('texts.token'),
        trans('texts.action'))
      ->setUrl(url('api/tokens/'))
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)
      ->setOptions('bAutoWidth', false)
      ->setOptions('aoColumns', [[ "sWidth"=> "40%" ], [ "sWidth"=> "40%" ], ["sWidth"=> "20%"]])
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[2]]])
      ->render('datatable') !!}

  <script>

    window.onDatatableReady = actionListHandler;

    function setTrashVisible() {
        var checked = $('#trashed').is(':checked');
        var url = '{{ URL::to('view_archive/token') }}' + (checked ? '/true' : '/false');

        $.get(url, function(data) {
            refreshDatatable();
        })
    }

  </script>

  @if (Utils::isNinja() && !Utils::isReseller())
    <p>&nbsp;</p>
    <script src="https://zapier.com/zapbook/embed/widget.js?services=invoice-ninja&container=false&limit=6"></script>
  @endif
  
@stop
