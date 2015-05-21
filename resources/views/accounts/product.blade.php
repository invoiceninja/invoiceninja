@extends('accounts.nav')

@section('content') 
  @parent

  {!! Former::open($url)->method($method)
      ->rules(['product_key' => 'required|max:20'])
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
  {!! Former::textarea('notes')->data_bind("value: wrapped_notes, valueUpdate: 'afterkeydown'") !!}
  {!! Former::text('cost') !!}

  </div>
  </div>

  {!! Former::actions( 
      Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')),
      Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/company/products'))->appendIcon(Icon::create('remove-circle'))      
  ) !!}

  {!! Former::close() !!}

  <script type="text/javascript">

  function ViewModel(data) {
    var self = this;
    @if ($product)
      self.notes = ko.observable(wordWrapText('{{ str_replace(["\r\n","\r","\n"], '\n', addslashes($product->notes)) }}', 300));
    @else
      self.notes = ko.observable('');
    @endif
    
    self.wrapped_notes = ko.computed({
      read: function() {
        return self.notes();
      },
      write: function(value) {
        value = wordWrapText(value, 235);
        self.notes(value);
      },
      owner: this
    });
  }

  window.model = new ViewModel();
  ko.applyBindings(model);  

  $(function() {
    $('#product_key').focus();
  });

  </script>

@stop