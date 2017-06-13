@extends('header')

@section('content')
  @parent

  {!! Former::open($url)->method($method)
      ->rules(['product_key' => 'required|max:255'])
      ->addClass('col-md-10 col-md-offset-1 warn-on-exit') !!}


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
  {!! Former::textarea('notes')->rows(6) !!}

  @if ($account->hasFeature(FEATURE_INVOICE_SETTINGS))
      @if ($account->custom_invoice_item_label1)
          {!! Former::text('custom_value1')->label($account->custom_invoice_item_label1) !!}
      @endif
      @if ($account->custom_invoice_item_label2)
          {!! Former::text('custom_value2')->label($account->custom_invoice_item_label2) !!}
      @endif
  @endif

  {!! Former::text('cost') !!}

  @if ($account->invoice_item_taxes)
    @include('partials.tax_rates')
  @endif

  </div>
  </div>

  {!! Former::actions(
      Button::normal(trans('texts.cancel'))->large()->asLinkTo(HTMLUtils::previousUrl('/products'))->appendIcon(Icon::create('remove-circle')),
      Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))
  ) !!}

  {!! Former::close() !!}

  <script type="text/javascript">

  $(function() {
    $('#product_key').focus();
  });

  </script>

@stop
