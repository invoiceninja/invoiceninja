@extends('header')

@section('content') 
  @parent

  @include('accounts.nav', ['selected' => ACCOUNT_PRODUCTS])

  {!! Former::open($url)->method($method)
      ->rules(['product_key' => 'required|max:255'])
      ->addClass('warn-on-exit') !!}


  <div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! $title !!}</h3>
  </div>
  <div class="panel-body form-padding-right">

  @if ($product)
    {{ Former::populate($product) }}
    {{ Former::populateField('cost', number_format($product->cost, 2, '.', '')) }}
  @endif

  {!! Former::text('product_key')->label('texts.product') !!}
  {!! Former::textarea('notes') !!}
  {!! Former::text('cost') !!}

  @if ($account->invoice_item_taxes)
      {!! Former::select('default_tax_rate_id')
            ->addOption('', '')
            ->label(trans('texts.tax_rate'))
            ->fromQuery($taxRates, function($model) { return $model->name . ' ' . $model->rate . '%'; }, 'id') !!}
  @endif

  </div>
  </div>

  {!! Former::actions( 
      Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/settings/products'))->appendIcon(Icon::create('remove-circle')),
      Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))
  ) !!}

  {!! Former::close() !!}

  <script type="text/javascript">

  $(function() {
    $('#product_key').focus();
  });

  </script>

@stop