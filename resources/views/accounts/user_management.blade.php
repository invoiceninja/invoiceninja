@extends('header')

@section('content')
	@parent
    @include('accounts.nav', ['selected' => ACCOUNT_USER_MANAGEMENT, 'advanced' => true])

    @if (Utils::hasFeature(FEATURE_USERS))
        <div class="pull-right">  
            {!! Button::primary(trans('texts.add_user'))->asLinkTo(URL::to('/users/create'))->appendIcon(Icon::create('plus-sign')) !!}
        </div>
    @elseif (Utils::isTrial())
        <div class="alert alert-warning">{!! trans('texts.add_users_not_supported') !!}</div>    
    @endif

    <label for="trashed" style="font-weight:normal; margin-left: 10px;">
        <input id="trashed" type="checkbox" onclick="setTrashVisible()"
            {!! Session::get('show_trash:user') ? 'checked' : ''!!}/> {!! trans('texts.show_archived_users')!!}
    </label>

  @include('partials.bulk_form', ['entityType' => ENTITY_USER])

  {!! Datatable::table()
      ->addColumn(
        trans('texts.name'),
        trans('texts.email'),
        trans('texts.user_state'),
        trans('texts.action'))
      ->setUrl(url('api/users/'))
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)
      ->setOptions('bAutoWidth', false)
      ->setOptions('aoColumns', [[ "sWidth"=> "20%" ], [ "sWidth"=> "45%" ], ["sWidth"=> "20%"], ["sWidth"=> "15%" ]])
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[3]]])
      ->render('datatable') !!}

  <script>
    
    window.onDatatableReady = actionListHandler;

    function setTrashVisible() {
        var checked = $('#trashed').is(':checked');
        var url = '{{ URL::to('view_archive/user') }}' + (checked ? '/true' : '/false');

        $.get(url, function(data) {
            refreshDatatable();
        })
    }

  </script>

@stop
