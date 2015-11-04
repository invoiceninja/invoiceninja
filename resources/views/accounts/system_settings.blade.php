@extends('header')

@section('content') 
    @parent

    {!! Former::open('/update_setup')->addClass('warn-on-exit') !!}

    @include('accounts.nav', ['selected' => ACCOUNT_SYSTEM_SETTINGS])

    <div class="row">

        {!! Former::open()->rules([
            'app[url]' => 'required',
            //'database[default]' => 'required',
            'database[type][host]' => 'required',
            'database[type][database]' => 'required',
            'database[type][username]' => 'required',
            'database[type][password]' => 'required',
          ]) !!}


        @include('partials.system_settings')

    </div>

    <center>
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
    </center>

    {!! Former::close() !!}

@stop

@section('onReady')
    $('#app\\[url\\]').focus();
@stop