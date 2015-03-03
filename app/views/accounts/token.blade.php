@extends('accounts.nav')

@section('content') 
  @parent

  {{ Former::open($url)->method($method)->addClass('col-md-8 col-md-offset-2 warn-on-exit')->rules(array(
      'name' => 'required',
  )); }}

  {{ Former::legend($title) }}

  <p>&nbsp;</p>

  @if ($token)
    {{ Former::populate($token) }}    
  @endif

  {{ Former::text('name') }}

  <p>&nbsp;</p>
  
  {{ Former::actions( 
      Button::lg_success_submit(trans('texts.save'))->append_with_icon('floppy-disk'),
      Button::lg_default_link('company/advanced_settings/token_management', 'Cancel')->append_with_icon('remove-circle')      
  ) }}

  {{ Former::close() }}

@stop