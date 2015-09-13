@extends('accounts.nav')

@section('content') 
  @parent

  {!! Former::open($url)->method($method)
      ->rules(['product_key' => 'required|max:255'])
      ->addClass('col-md-8 col-md-offset-2 warn-on-exit') !!}


  <div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! $title !!}</h3>
  </div>
  <div class="panel-body">

  @if ($product)
    {{ Former::populate($product) }}
    {{ Former::populateField('cost', number_format($product->cost, 2, '.', '')) }}
  @endif

  {!! Former::text('product_key')->label('texts.product') !!}
  {!! Former::textarea('notes') !!}
  {!! Former::text('cost') !!}

  </div>
  </div>

  {!! Former::actions( 
      Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/company/products'))->appendIcon(Icon::create('remove-circle')),
      Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))
  ) !!}

  {!! Former::close() !!}

  <script type="text/javascript">

  $(function() {
    $('#product_key').focus();
  });

  </script>

@stop