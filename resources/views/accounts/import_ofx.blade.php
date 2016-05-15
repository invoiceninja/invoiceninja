@extends('header')

@section('content')
    @parent

    @include('accounts.nav', ['selected' => ACCOUNT_BANKS])

    <div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{{ trans('texts.import_ofx') }}</h3>
    </div>
    <div class="panel-body">

        {!! Former::open_for_files('bank_accounts/import_ofx')
                ->rules(['ofx_file' => 'required'])
                ->addClass('warn-on-exit') !!}

        {!! Former::file("ofx_file") !!}

        {!! Former::actions( Button::info(trans('texts.upload'))->submit()->large()->appendIcon(Icon::create('open'))) !!}
        {!! Former::close() !!}

    </div>
    </div>

@stop
