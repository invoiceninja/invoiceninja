@extends('header')

@section('content') 
  @parent
  @include('accounts.nav', ['selected' => ACCOUNT_API_TOKENS])

  {!! Former::open($url)->method($method)->addClass('warn-on-exit')->rules(array(
      'name' => 'required',
  )); !!}

<div class="panel panel-default">
<div class="panel-heading">
    <h3 class="panel-title">{!! trans($title) !!}</h3>
</div>
<div class="panel-body form-padding-right">

  @if ($token)
    {!! Former::populate($token) !!}    
  @endif

  {!! Former::text('name') !!}

</div>
</div>

    @if (Auth::user()->hasFeature(FEATURE_API))
      {!! Former::actions( 
          Button::normal(trans('texts.cancel'))->asLinkTo(URL::to('/settings/api_tokens'))->appendIcon(Icon::create('remove-circle'))->large(),
          Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))
      ) !!}
    @else
        <script>
            $(function() {
                $('form.warn-on-exit input').prop('disabled', true);
            });
        </script>
    @endif
  

  {!! Former::close() !!}

@stop

@section('onReady')
    $('#name').focus();
@stop