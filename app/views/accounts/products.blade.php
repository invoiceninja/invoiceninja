@extends('accounts.nav')

@section('content') 
  @parent

  {{ Former::open()->addClass('col-md-10 col-md-offset-1 warn-on-exit') }}
  {{ Former::populateField('fill_products', intval($account->fill_products)) }}
  {{ Former::populateField('update_products', intval($account->update_products)) }}


  {{ Former::legend('products') }}
  {{ Former::checkbox('fill_products')->text(trans('texts.fill_products_help')) }}
  {{ Former::checkbox('update_products')->text(trans('texts.update_products_help')) }}

  {{ Former::actions( Button::lg_success_submit(trans('texts.save'))->append_with_icon('floppy-disk') ) }}
  {{ Former::close() }}

  {{ Button::success_link(URL::to('company/products/create'), trans("texts.create_product"), array('class' => 'pull-right'))->append_with_icon('plus-sign') }} 

  {{ Datatable::table()   
      ->addColumn(
        trans('texts.product'),
        trans('texts.description'),
        trans('texts.unit_cost'),
        trans('texts.action'))
      ->setUrl(url('api/products/'))      
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)
      ->render('datatable') }}

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