@extends('header')

@section('content')
  @parent

  @include('accounts.nav', ['selected' => ACCOUNT_TAX_RATES])

  {!! Former::open($url)->method($method)
      ->rules([
        'name' => 'required',
        'rate' => 'required',
        'is_inclusive' => 'required',
       ])
      ->addClass('warn-on-exit') !!}


  <div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! $title !!}</h3>
  </div>
  <div class="panel-body form-padding-right">

  @if ($taxRate)
    {{ Former::populate($taxRate) }}
    {{ Former::populateField('is_inclusive', intval($taxRate->is_inclusive)) }}
  @endif

  {!! Former::text('name')->label('texts.name') !!}
  {!! Former::text('rate')->label('texts.rate')->append('%') !!}

  @if (! $taxRate && ! auth()->user()->account->inclusive_taxes)
      {!! Former::radios('is_inclusive')->radios([
              trans('texts.exclusive') . ': 100 + 10% = 100 + 10' => array('name' => 'is_inclusive', 'value' => 0),
              trans('texts.inclusive') . ':&nbsp; 100 + 10% = 90.91 + 9.09' => array('name' => 'is_inclusive', 'value' => 1),
          ])->check(0)
            ->label('type')
            ->help('tax_rate_type_help') !!}
  @endif

  </div>
  </div>

  <center class="buttons">
      {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/settings/tax_rates'))->appendIcon(Icon::create('remove-circle')) !!}
      {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
  </center>

  {!! Former::close() !!}

  <script type="text/javascript">

  $(function() {
    $('#name').focus();
  });

  </script>

@stop
