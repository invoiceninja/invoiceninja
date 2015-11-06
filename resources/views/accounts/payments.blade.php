@extends('header')

@section('content')	
	@parent	
    @include('accounts.nav', ['selected' => ACCOUNT_PAYMENTS])

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
  </script>

@stop