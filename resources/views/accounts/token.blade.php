@extends('accounts.nav')

@section('content') 
  @parent
  @include('accounts.nav_advanced')

  {!! Former::open($url)->method($method)->addClass('col-md-8 col-md-offset-2 warn-on-exit')->rules(array(
      'name' => 'required',
  )); !!}

<div class="panel panel-default">
<div class="panel-heading">
    <h3 class="panel-title">{!! trans($title) !!}</h3>
</div>
<div class="panel-body">

  @if ($token)
    {!! Former::populate($token) !!}    
  @endif

  {!! Former::text('name') !!}

</div>
</div>
  
  {!! Former::actions( 
      Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')),
      Button::normal(trans('texts.cancel'))->asLinkTo('/company/advanced_settings/user_management')->appendIcon(Icon::create('remove-circle'))->large()
  ) !!}

  {!! Former::close() !!}

@stop