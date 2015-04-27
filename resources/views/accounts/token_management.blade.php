@extends('accounts.nav')

@section('content')
	@parent
	@include('accounts.nav_advanced')

  {!! Former::open('tokens/delete')->addClass('user-form') !!}

  <div style="display:none">
    {!! Former::text('tokenPublicId') !!}
  </div>
  {!! Former::close() !!}


  <div class="pull-right">
  {!! Button::normal(trans('texts.view_documentation'))->asLinkTo(NINJA_WEB_URL.'/knowledgebase/api-documentation/')->withAttributes(['target' => '_blank']) !!}
  @if (Utils::isPro())
    {!! Button::primary(trans('texts.add_token'))->asLinkTo('/tokens/create')->appendIcon(Icon::create('plus-sign')) !!}
  @endif
  </div>

  <!--
    <label for="trashed" style="font-weight:normal; margin-left: 10px;">
        <input id="trashed" type="checkbox" onclick="setTrashVisible()"
            {!! Session::get('show_trash:token') ? 'checked' : ''!!}/> {!! trans('texts.show_deleted_tokens')!!}
    </label>
  -->

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
  window.onDatatableReady = function() {
    $('tbody tr').mouseover(function() {
      $(this).closest('tr').find('.tr-action').css('visibility','visible');
    }).mouseout(function() {
      $dropdown = $(this).closest('tr').find('.tr-action');
      if (!$dropdown.hasClass('open')) {
        $dropdown.css('visibility','hidden');
      }
    });
  }

    function setTrashVisible() {
        var checked = $('#trashed').is(':checked');
        window.location = '{!! URL::to('view_archive/token') !!}' + (checked ? '/true' : '/false');
    }

  function deleteToken(id) {
    if (!confirm('Are you sure?')) {
      return;
    }

    $('#tokenPublicId').val(id);
    $('form.user-form').submit();
  }
  </script>

@stop
