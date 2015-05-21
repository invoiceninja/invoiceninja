@extends('accounts.nav')

@section('content') 
  @parent

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
      {!! Former::actions( Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) ) !!}
      {!! Former::close() !!}
  </div>
  </div>

  {!! Button::primary(trans('texts.create_product'))
        ->asLinkTo(URL::to('/products/create'))
        ->withAttributes(['class' => 'pull-right'])
        ->appendIcon(Icon::create('plus-sign')) !!}

  {!! Datatable::table()   
      ->addColumn(
        trans('texts.product'),
        trans('texts.description'),
        trans('texts.unit_cost'),
        trans('texts.action'))
      ->setUrl(url('api/products/'))      
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)      
      ->setOptions('bAutoWidth', false)      
      ->setOptions('aoColumns', [[ "sWidth"=> "20%" ], [ "sWidth"=> "45%" ], ["sWidth"=> "20%"], ["sWidth"=> "15%" ]])      
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[3]]])
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