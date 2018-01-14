@extends('header')

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_API_TOKENS])

    {!! Former::open($url)->method($method)->addClass('warn-on-exit')->rules(array(
        'event_id' => 'required',
        'target_url' => 'required|url',
        //'format' => 'required',
    )); !!}

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans($title) !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            @if ($subscription)
                {!! Former::populate($subscription) !!}
            @endif

            {!! Former::select('event_id')
                    ->options([
                        trans('texts.clients') => [
                            EVENT_CREATE_CLIENT => trans('texts.subscription_event_' . EVENT_CREATE_CLIENT),
                            EVENT_UPDATE_CLIENT => trans('texts.subscription_event_' . EVENT_UPDATE_CLIENT),
                            EVENT_DELETE_CLIENT => trans('texts.subscription_event_' . EVENT_DELETE_CLIENT),
                        ],
                        trans('texts.invoices') => [
                            EVENT_CREATE_INVOICE => trans('texts.subscription_event_' . EVENT_CREATE_INVOICE),
                            EVENT_UPDATE_INVOICE => trans('texts.subscription_event_' . EVENT_UPDATE_INVOICE),
                            EVENT_DELETE_INVOICE => trans('texts.subscription_event_' . EVENT_DELETE_INVOICE),
                        ],
                        trans('texts.payments') => [
                            EVENT_CREATE_PAYMENT => trans('texts.subscription_event_' . EVENT_CREATE_PAYMENT),
                            EVENT_DELETE_PAYMENT => trans('texts.subscription_event_' . EVENT_DELETE_PAYMENT),
                        ],
                        trans('texts.quotes') => [
                            EVENT_CREATE_QUOTE => trans('texts.subscription_event_' . EVENT_CREATE_QUOTE),
                            EVENT_UPDATE_QUOTE => trans('texts.subscription_event_' . EVENT_UPDATE_QUOTE),
                            EVENT_APPROVE_QUOTE => trans('texts.subscription_event_' . EVENT_APPROVE_QUOTE),
                            EVENT_DELETE_QUOTE => trans('texts.subscription_event_' . EVENT_DELETE_QUOTE),
                        ],
                        trans('texts.tasks') => [
                            EVENT_CREATE_TASK => trans('texts.subscription_event_' . EVENT_CREATE_TASK),
                            EVENT_UPDATE_TASK => trans('texts.subscription_event_' . EVENT_UPDATE_TASK),
                            EVENT_DELETE_TASK => trans('texts.subscription_event_' . EVENT_DELETE_TASK),
                        ],
                        trans('texts.vendors') => [
                            EVENT_CREATE_VENDOR => trans('texts.subscription_event_' . EVENT_CREATE_VENDOR),
                            EVENT_UPDATE_VENDOR => trans('texts.subscription_event_' . EVENT_UPDATE_VENDOR),
                            EVENT_DELETE_VENDOR => trans('texts.subscription_event_' . EVENT_DELETE_VENDOR),
                        ],
                        trans('texts.expenses') => [
                            EVENT_CREATE_EXPENSE => trans('texts.subscription_event_' . EVENT_CREATE_EXPENSE),
                            EVENT_UPDATE_EXPENSE => trans('texts.subscription_event_' . EVENT_UPDATE_EXPENSE),
                            EVENT_DELETE_EXPENSE => trans('texts.subscription_event_' . EVENT_DELETE_EXPENSE),
                        ],
                    ])
                    ->label('event') !!}

            {!! Former::text('target_url')
                    ->placeholder('https://example.com')!!}

            <!--
            {!! Former::select('format')
                    ->options([
                        SUBSCRIPTION_FORMAT_JSON => SUBSCRIPTION_FORMAT_JSON,
                        SUBSCRIPTION_FORMAT_UBL => SUBSCRIPTION_FORMAT_UBL
                    ])
                    ->help('target_url_help') !!}
            -->
            
        </div>
    </div>

    @if (Auth::user()->hasFeature(FEATURE_API))
        <center class="buttons">
            {!! Button::normal(trans('texts.cancel'))->asLinkTo(URL::to('/settings/api_tokens'))->appendIcon(Icon::create('remove-circle'))->large() !!}
            {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
        </center>
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
