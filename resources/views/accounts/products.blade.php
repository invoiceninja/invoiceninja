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

      {!! Former::checkbox('fill_products')->text(trans('texts.fill_products_help'))->value(1) !!}
      {!! Former::checkbox('update_products')->text(trans('texts.update_products_help'))->value(1) !!}
      &nbsp;
      {!! Former::actions( Button::success(trans('texts.save'))->submit()->appendIcon(Icon::create('floppy-disk')) ) !!}
      {!! Former::close() !!}
  </div>
  </div>

@stop
