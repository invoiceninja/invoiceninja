@extends('header')

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_API_TOKENS])

    {!! Former::open($url)->method($method)->addClass('warn-on-exit')->rules(array(
        'event_id' => 'required',
        'target_url' => 'required|url',
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
                            EVENT_CREATE_CLIENT => trans('texts.event_create_client'),
                        ],
                        trans('texts.invoices') => [
                            EVENT_CREATE_INVOICE => trans('texts.event_create_invoice'),
                            EVENT_UPDATE_INVOICE => trans('texts.event_update_invoice'),
                            EVENT_DELETE_INVOICE => trans('texts.event_delete_invoice'),
                        ],
                        trans('texts.payments') => [
                            EVENT_CREATE_PAYMENT => trans('texts.event_create_payment'),
                        ],
                        trans('texts.quotes') => [
                            EVENT_CREATE_QUOTE => trans('texts.event_create_quote'),
                            EVENT_UPDATE_QUOTE => trans('texts.event_update_quote'),
                            EVENT_DELETE_QUOTE => trans('texts.event_delete_quote'),
                        ]
                    ])
                    ->label('event') !!}

            {!! Former::text('target_url')
                    ->placeholder('https://example.com')!!}

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
