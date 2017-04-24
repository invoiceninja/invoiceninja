@extends('header')

@section('content')
  @parent

  @include('accounts.nav', ['selected' => ACCOUNT_PAYMENT_TERMS])

  {!! Former::open($url)->method($method)
      ->rules([
        'num_days' => 'required'
       ])
      ->addClass('warn-on-exit') !!}


  <div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! $title !!}</h3>
  </div>
  <div class="panel-body form-padding-right">

  @if ($paymentTerm)
    {{ Former::populate($paymentTerm) }}
  @endif

  {!! Former::text('num_days')
        ->type('number')
        ->min(1)
        ->label('texts.num_days') !!}

  </div>
  </div>

  {!! Former::actions(
      Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/settings/payment_terms'))->appendIcon(Icon::create('remove-circle')),
      Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))
  ) !!}

  {!! Former::close() !!}

  <script type="text/javascript">

  $(function() {
    $('#name').focus();
  });

  </script>

@stop
