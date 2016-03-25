@extends('header')

@section('content') 
    @parent

    {!! Former::open_for_files()->addClass('warn-on-exit') !!}
    {{ Former::populate($account) }}
    {{ Former::populateField('military_time', intval($account->military_time)) }}
    {{ Former::populateField('show_currency_code', intval($account->show_currency_code)) }}

    @include('accounts.nav', ['selected' => ACCOUNT_LOCALIZATION])

    <div class="row">
        
        <div class="col-md-12">

            <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">{!! trans('texts.localization') !!}</h3>
            </div>
                <div class="panel-body form-padding-right">

                {!! Former::select('currency_id')->addOption('','')
                    ->fromQuery($currencies, 'name', 'id') !!}          
                {!! Former::select('language_id')->addOption('','')
                    ->fromQuery($languages, 'name', 'id') !!}           
                {!! Former::select('timezone_id')->addOption('','')
                    ->fromQuery($timezones, 'location', 'id') !!}
                {!! Former::select('date_format_id')->addOption('','')
                    ->fromQuery($dateFormats, 'label', 'id') !!}
                {!! Former::select('datetime_format_id')->addOption('','')
                    ->fromQuery($datetimeFormats, 'label', 'id') !!}
                {!! Former::checkbox('military_time')->text(trans('texts.enable')) !!}
                {{-- Former::checkbox('show_currency_code')->text(trans('texts.enable')) --}}

                </div>
            </div>
        </div>
    </div>

    <center>
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
    </center>

    {!! Former::close() !!}

@stop

@section('onReady')
    $('#currency_id').focus();
@stop