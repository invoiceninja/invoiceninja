@extends('accounts.nav')

@section('content') 
  @parent
  @include('accounts.nav_advanced')

  {!! Former::open($url)->method($method)->addClass('col-md-8 col-md-offset-2 warn-on-exit')->rules(array(
      'first_name' => 'required',
      'last_name' => 'required',
      'email' => 'required|email',
  )); !!}

  {!! Former::legend($title) !!}

  @if ($user)
    {!! Former::populate($user) !!}    
  @endif

<div class="panel panel-default">
<div class="panel-body">

  {!! Former::text('first_name') !!}
  {!! Former::text('last_name') !!}
  {!! Former::text('email') !!}

</div>
</div>

  {!! Former::actions( 
      Button::success(trans($user && $user->confirmed ? 'texts.save' : 'texts.send_invite'))->submit()->large()->appendIcon(Icon::create($user && $user->confirmed ? 'floppy-disk' : 'send')),
      Button::normal(trans('texts.cancel'))->asLinkTo('/company/advanced_settings/user_management')->appendIcon(Icon::create('remove-circle'))->large()
  )!!}

  {!! Former::close() !!}

@stop