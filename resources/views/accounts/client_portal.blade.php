@extends('header')

@section('head')
    @parent

    <link href='https://fonts.googleapis.com/css?family=Roboto+Mono' rel='stylesheet' type='text/css'>
@stop

@section('content')	
	@parent
	
	{!! Former::open_for_files()
                ->addClass('warn-on-exit') !!}

    {!! Former::populateField('client_view_css', $client_view_css) !!}

    @if (!Utils::isNinja() && !Auth::user()->account->isWhiteLabel())
    <div class="alert alert-warning" style="font-size:larger;">
    <center>
        {!! trans('texts.white_label_custom_css', ['link'=>'<a href="#" onclick="$(\'#whiteLabelModal\').modal(\'show\');">'.trans('texts.white_label_purchase_link').'</a>']) !!}
    </center>
    </div>
    @endif

    @include('accounts.nav', ['selected' => ACCOUNT_CLIENT_PORTAL])

	<div class="row">
		<div class="col-md-12">

        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.custom_css') !!}</h3>
          </div>
            <div class="panel-body">
              {!! Former::textarea('client_view_css')
                        ->label(trans('texts.custom_css'))
                        ->rows(15)
                        ->autofocus()
                        ->maxlength(60000)
                        ->style("min-width:100%;max-width:100%;font-family:'Roboto Mono', 'Lucida Console', Monaco, monospace;font-size:14px;'") !!}
            </div>
        </div>
        </div>
    </div>
	
	<center>
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

    {!! Former::close() !!}

@stop