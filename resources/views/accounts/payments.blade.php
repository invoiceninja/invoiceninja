@extends('accounts.nav')

@section('content')	
	@parent	

  {!! Former::open('gateways/delete')->addClass('user-form') !!}

  <div style="display:none">
    {!! Former::text('accountGatewayPublicId') !!}
  </div>
  {!! Former::close() !!}


  @if ($showAdd)
      {!! Button::primary(trans('texts.add_gateway'))
            ->asLinkTo(URL::to('/gateways/create'))
            ->withAttributes(['class' => 'pull-right'])
            ->appendIcon(Icon::create('plus-sign')) !!}
  @endif

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

  /*
    function setTrashVisible() {
        var checked = $('#trashed').is(':checked');
        window.location = '{{ URL::to('view_archive/token') }}' + (checked ? '/true' : '/false');
    }
  */

  function deleteAccountGateway(id) {
    if (!confirm("{!! trans('texts.are_you_sure') !!}")) {
      return;
    }

    $('#accountGatewayPublicId').val(id);
    $('form.user-form').submit();
  }
  </script>

@stop