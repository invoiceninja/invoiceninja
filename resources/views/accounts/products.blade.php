@extends('header')

@section('content') 
  @parent

  @include('accounts.nav', ['selected' => ACCOUNT_PRODUCTS])

  {!! Former::open()->addClass('warn-on-exit') !!}
  {{ Former::populateField('fill_products', intval($account->fill_products)) }}
  {{ Former::populateField('update_products', intval($account->update_products)) }}


  <div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! trans('texts.product_settings') !!}</h3>
  </div>  
  <div class="panel-body">

      {!! Former::checkbox('fill_products')->text(trans('texts.fill_products_help')) !!}
      {!! Former::checkbox('update_products')->text(trans('texts.update_products_help')) !!}
      &nbsp;
      {!! Former::actions( Button::success(trans('texts.save'))->submit()->appendIcon(Icon::create('floppy-disk')) ) !!}
      {!! Former::close() !!}
  </div>
  </div>

  {!! Button::primary(trans('texts.create_product'))
        ->asLinkTo(URL::to('/products/create'))
        ->withAttributes(['class' => 'pull-right'])
        ->appendIcon(Icon::create('plus-sign')) !!}

  @include('partials.bulk_form', ['entityType' => ENTITY_PRODUCT])

  {!! Datatable::table()   
      ->addColumn($columns)
      ->setUrl(url('api/products/'))      
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)      
      ->setOptions('bAutoWidth', false)      
      //->setOptions('aoColumns', [[ "sWidth"=> "15%" ], [ "sWidth"=> "35%" ]])
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[3]]])
      ->render('datatable') !!}

  <script>
    window.onDatatableReady = actionListHandler;
  </script>  


@stop