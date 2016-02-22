@extends('header')

@section('content')
	@parent
    @include('accounts.nav', ['selected' => ACCOUNT_USER_MANAGEMENT, 'advanced' => true])


  <div class="pull-right">  
    @if (Utils::isPro() && ! Utils::isTrial())
        {!! Button::primary(trans('texts.add_user'))->asLinkTo(URL::to('/users/create'))->appendIcon(Icon::create('plus-sign')) !!}
    @endif
  </div>


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
