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
      Button::normal(trans('texts.cancel'))->asLinkTo(URL::to('/company/advanced_settings/token_management'))->appendIcon(Icon::create('remove-circle'))->large(),
      Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))
  ) !!}

  {!! Former::close() !!}

@stop

@section('onReady')
    $('#name').focus();
@stop