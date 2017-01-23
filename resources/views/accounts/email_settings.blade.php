@extends('header')

@section('head')
    @parent

    <style type="text/css">
        .iframe_url {
            display: none;
        }
    </style>
@stop

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_EMAIL_SETTINGS, 'advanced' => true])

    {!! Former::open()->rules([
            'bcc_email' => 'email',
        ])->addClass('warn-on-exit') !!}

    {{ Former::populate($account) }}
    {{ Former::populateField('pdf_email_attachment', intval($account->pdf_email_attachment)) }}
    {{ Former::populateField('document_email_attachment', intval($account->document_email_attachment)) }}
    {{ Former::populateField('enable_email_markup', intval($account->enable_email_markup)) }}

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.email_settings') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            {!! Former::checkbox('pdf_email_attachment')
                    ->text(trans('texts.enable'))
                    ->value(1)
                    ->help( ! Utils::isNinja() ? (env('PHANTOMJS_BIN_PATH') ? 'phantomjs_local' : trans('texts.phantomjs_help', [
                        'link_phantom' => link_to('https://phantomjscloud.com/', 'phantomjscloud.com', ['target' => '_blank']),
                        'link_docs' => link_to('https://www.invoiceninja.com/self-host/#phantomjs', 'PhantomJS', ['target' => '_blank'])
                    ])) : false) !!}

            {!! Former::checkbox('document_email_attachment')
                    ->text(trans('texts.enable'))
                    ->value(1) !!}

            &nbsp;

            {!! Former::text('bcc_email')->help('bcc_email_help') !!}

            &nbsp;

            {{-- Former::select('recurring_hour')->options($recurringHours) --}}

        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.email_design') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            {!! Former::select('email_design_id')
                        ->appendIcon('question-sign')
                        ->addGroupClass('email_design_id')
                        ->addOption(trans('texts.plain'), EMAIL_DESIGN_PLAIN)
                        ->addOption(trans('texts.light'), EMAIL_DESIGN_LIGHT)
                        ->addOption(trans('texts.dark'), EMAIL_DESIGN_DARK)
                        ->help(trans('texts.email_design_help')) !!}

            &nbsp;

            @if (Utils::isNinja())
                {!! Former::checkbox('enable_email_markup')
                        ->text(trans('texts.enable') .
                            '<a href="'.EMAIL_MARKUP_URL.'" target="_blank" title="'.trans('texts.learn_more').'">' . Icon::create('question-sign') . '</a> ')
                        ->help(trans('texts.enable_email_markup_help'))
                        ->value(1) !!}
            @endif
        </div>
    </div>

    @if (Auth::user()->hasFeature(FEATURE_CUSTOM_EMAILS))
        <center>
            {!! Button::success(trans('texts.save'))->large()->submit()->appendIcon(Icon::create('floppy-disk')) !!}
        </center>
    @endif

    <div class="modal fade" id="designHelpModal" tabindex="-1" role="dialog" aria-labelledby="designHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="min-width:150px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="designHelpModalLabel">{{ trans('texts.email_designs') }}</h4>
                </div>

                <div class="modal-body">
                    <div class="row" style="text-align:center">
                        <div class="col-md-4">
                            <h4>{{ trans('texts.plain') }}</h4><br/>
                            <img src="{{ asset('images/emails/plain.png') }}" class="img-responsive"/>
                        </div>
                        <div class="col-md-4">
                            <h4>{{ trans('texts.light') }}</h4><br/>
                            <img src="{{ asset('images/emails/light.png') }}" class="img-responsive"/>
                        </div>
                        <div class="col-md-4">
                            <h4>{{ trans('texts.dark') }}</h4><br/>
                            <img src="{{ asset('images/emails/dark.png') }}" class="img-responsive"/>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="margin-top: 0px">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
                </div>

            </div>
        </div>
    </div>

    {!! Former::close() !!}

@stop
