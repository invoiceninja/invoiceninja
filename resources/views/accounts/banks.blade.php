@extends('header')

@section('content')	
	@parent	
    @include('accounts.nav', ['selected' => ACCOUNT_BANKS])

  {!! Button::primary(trans('texts.add_bank_account'))
        ->asLinkTo(URL::to('/bank_accounts/create'))
        ->withAttributes(['class' => 'pull-right'])
        ->appendIcon(Icon::create('plus-sign')) !!}

  @include('partials.bulk_form', ['entityType' => ENTITY_BANK_ACCOUNT])

  {!! Datatable::table()
      ->addColumn(
        trans('texts.name'),
        trans('texts.integration_type'),
        trans('texts.action'))
      ->setUrl(url('api/bank_accounts/'))
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)
      ->setOptions('bAutoWidth', false)
      ->setOptions('aoColumns', [[ "sWidth"=> "50%" ], [ "sWidth"=> "30%" ], ["sWidth"=> "20%"]])
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[2]]])
      ->render('datatable') !!}

  <script>
    window.onDatatableReady = actionListHandler;
  </script>

@stop