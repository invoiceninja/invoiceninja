@extends('header')

@section('head')
    @parent

    @include('money_script')
@stop

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

                {!! Former::select('currency_id')
                        ->addOption('','')
                        ->fromQuery($currencies, 'name', 'id')
                        ->onchange('updateCurrencyCodeRadio()') !!}
                {!! Former::radios('show_currency_code')->radios([
                        trans('texts.currency_symbol') . ': <span id="currency_symbol_example"/>' => array('name' => 'show_currency_code', 'value' => 0),
                        trans('texts.currency_code') . ': <span id="currency_code_example"/>' => array('name' => 'show_currency_code', 'value' => 1),
                    ])->inline()
                        ->label('&nbsp;')
                        ->addGroupClass('currrency_radio') !!}
                <br/>

                {!! Former::select('language_id')->addOption('','')
                    ->fromQuery($languages, 'name', 'id')
                    ->help(trans('texts.translate_app', ['link' => link_to(TRANSIFEX_URL, 'Transifex.com', ['target' => '_blank'])])) !!}
                <br/>&nbsp;<br/>

                {!! Former::select('timezone_id')->addOption('','')
                    ->fromQuery($timezones, 'location', 'id') !!}
                {!! Former::select('date_format_id')->addOption('','')
                    ->fromQuery($dateFormats) !!}
                {!! Former::select('datetime_format_id')->addOption('','')
                    ->fromQuery($datetimeFormats) !!}
                {!! Former::checkbox('military_time')->text(trans('texts.enable'))->value(1) !!}

                <br/>&nbsp;<br/>

                {!! Former::select('start_of_week')->addOption('','')
                    ->fromQuery($weekdays)
                    ->help('start_of_week_help') !!}

                {!! Former::select('financial_year_start')
                        ->addOption('','')
                        ->options($months)
                        ->help('financial_year_start_help') !!}


                </div>
            </div>
        </div>
    </div>

    <center>
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
    </center>

    {!! Former::close() !!}

    <script type="text/javascript">

        function updateCurrencyCodeRadio() {
            var currencyId = $('#currency_id').val();
            var currency = currencyMap[currencyId];
            var symbolExample = '';
            var codeExample = '';

            if ( ! currency || ! currency.symbol) {
                $('.currrency_radio').hide();
            } else {
                symbolExample = formatMoney(1000, currencyId, {{ Auth::user()->account->country_id ?: DEFAULT_COUNTRY }}, '{{ CURRENCY_DECORATOR_SYMBOL }}');
                codeExample = formatMoney(1000, currencyId, {{ Auth::user()->account->country_id ?: DEFAULT_COUNTRY }}, '{{ CURRENCY_DECORATOR_CODE }}');
                $('.currrency_radio').show();
            }

            $('#currency_symbol_example').text(symbolExample);
            $('#currency_code_example').text(codeExample);
        }

    </script>

@stop

@section('onReady')
    $('#currency_id').focus();
    updateCurrencyCodeRadio();
@stop
