@extends('accounts.nav')

@section('content') 
  @parent
  @include('accounts.nav_advanced')

  {{ Former::open($url)->method($method)->addClass('col-md-8 col-md-offset-2 warn-on-exit')->rules(array(
      'first_name' => 'required',
      'last_name' => 'required',
      'email' => 'required|email',
  )); }}

  {{ Former::legend($title) }}

  @if ($user)
    {{ Former::populate($user) }}    
  @endif

  {{ Former::text('first_name') }}
  {{ Former::text('last_name') }}
  {{ Former::text('email') }}

  {{ Former::actions( 
      Button::lg_success_submit(trans($user && $user->confirmed ? 'texts.save' : 'texts.send_invite'))->append_with_icon($user && $user->confirmed ? 'floppy-disk' : 'send'),
      Button::lg_default_link('company/advanced_settings/user_management', trans('texts.cancel'))->append_with_icon('remove-circle')      
  )}}

  {{ Former::close() }}

@stop