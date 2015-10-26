@extends('header')

@section('content') 
  @parent

  @include('accounts.nav', ['selected' => ACCOUNT_TAX_RATES])

  {!! Former::open($url)->method($method)
      ->rules([
        'name' => 'required',
        'rate' => 'required'
       ])
      ->addClass('warn-on-exit') !!}


  <div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! $title !!}</h3>
  </div>
  <div class="panel-body form-padding-right">

  @if ($taxRate)
    {{ Former::populate($taxRate) }}
  @endif

  {!! Former::text('name')->label('texts.name') !!}
  {!! Former::text('rate')->label('texts.rate')->append('%') !!}

  </div>
  </div>

  {!! Former::actions( 
      Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/settings/tax_rates'))->appendIcon(Icon::create('remove-circle')),
      Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))
  ) !!}

  {!! Former::close() !!}

  <script type="text/javascript">

  $(function() {
    $('#name').focus();
  });

  </script>

@stop