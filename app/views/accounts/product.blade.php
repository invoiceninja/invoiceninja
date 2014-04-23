@extends('accounts.nav')

@section('content') 
  @parent

  {{ Former::open($url)->addClass('col-md-8 col-md-offset-2 warn-on-exit') }}


  {{ Former::legend($title) }}

  @if ($product)
    {{ Former::populate($product) }}
    {{ Former::populateField('cost', number_format($product->cost, 2)) }}
  @endif

  {{ Former::text('product_key') }}
  {{ Former::textarea('notes') }}
  {{ Former::text('cost') }}

  {{ Former::actions( 
      Button::lg_success_submit(trans('texts.save'))->append_with_icon('floppy-disk'),
      Button::lg_default_link('company/products', 'Cancel')->append_with_icon('remove-circle')      
  ) }}

  {{ Former::close() }}

@stop